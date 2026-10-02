#!/usr/bin/env bash
# =============================================================================
# Tests de bin/deploy-nightly.sh (#421) — git/composer/php mockés via des
# stubs placés en tête de PATH, comme prévu au plan #421 §8 étape 3.
# =============================================================================

# ── Fixture : une instance factice avec son propre PATH de stubs ────────────
_setup_fixture() {
    FIXTURE_DIR="$(mktemp -d)"
    STUB_BIN="${FIXTURE_DIR}/bin"
    mkdir -p "$STUB_BIN" "${FIXTURE_DIR}/instance/templates" "${FIXTURE_DIR}/instance/var/log"

    # Log des commandes appelées par les stubs, dans l'ordre
    CALL_LOG="${FIXTURE_DIR}/calls.log"
    touch "$CALL_LOG"

    export FIXTURE_DIR STUB_BIN CALL_LOG
    export PATH="${STUB_BIN}:${PATH}"
    hash -r  # bash met en cache la résolution des commandes ; sans ce reset,
             # "git"/"composer" continuent de pointer vers les vrais binaires
             # malgré le nouveau PATH, dans un shell persistant entre les tests.

    # deploy-nightly.sh résout PHP/composer en chemin ABSOLU par défaut (un
    # cron cPanel a un PATH minimal qui ne les contient pas) — les tests
    # doivent donc pointer explicitement vers les stubs au lieu de compter
    # sur $PATH pour les intercepter.
    export DEPLOY_NIGHTLY_PHP_BIN="${STUB_BIN}/php"
    export DEPLOY_NIGHTLY_COMPOSER_PHAR="${STUB_BIN}/composer.phar"
}

_teardown_fixture() {
    rm -rf "$FIXTURE_DIR"
    unset DEPLOY_NIGHTLY_PHP_BIN DEPLOY_NIGHTLY_COMPOSER_PHAR
    unset STUB_HEAD_SHA STUB_VENDOR_INCOMPLETE STUB_COMPOSER_INSTALL_EXIT STUB_CACHE_CLEAR_EXIT STUB_CACHE_CLEAR_FAIL_ONCE STUB_MIGRATIONS_EXIT
    hash -r
}

# Stub git : gère fetch/checkout/rev-parse. REMOTE_SHA doit être défini par le test.
_write_git_stub() {
    local remote_sha="$1"
    cat > "${STUB_BIN}/git" <<EOF
#!/bin/bash
echo "git \$*" >> "${CALL_LOG}"
case "\$1" in
    fetch) exit 0 ;;
    rev-parse)
        # HEAD = code réellement en place (peut différer de .deployed-sha) ;
        # tout le reste (origin/main) = SHA distant.
        if [[ "\$2" == "HEAD" ]]; then echo "\${STUB_HEAD_SHA:-head0000}"; else echo "${remote_sha}"; fi
        exit 0
        ;;
    checkout) exit \${GIT_CHECKOUT_EXIT:-0} ;;
    *) exit 0 ;;
esac
EOF
    chmod +x "${STUB_BIN}/git"
}

_write_passthrough_stub() {
    local name="$1" exit_code="${2:-0}"
    # Shebang en chemin ABSOLU : un stub nommé "bash" avec "#!/usr/bin/env bash"
    # se retrouverait lui-même via le PATH pollué (STUB_BIN en tête) et
    # boucle indéfiniment au lieu de s'exécuter.
    cat > "${STUB_BIN}/${name}" <<EOF
#!/bin/bash
echo "${name} \$*" >> "${CALL_LOG}"
exit ${exit_code}
EOF
    chmod +x "${STUB_BIN}/${name}"
}

# Stub php : sert à la fois pour composer.phar (via PHP CLI explicite, #570) et
# pour bin/console. Réglable par variables d'environnement exportées par le test :
#   STUB_VENDOR_INCOMPLETE=1|once  composer --dry-run affiche son aide (simule le CGI, exit 0) — toujours / au 1er appel seulement
#   STUB_COMPOSER_INSTALL_EXIT code de sortie de "composer install"
#   STUB_CACHE_CLEAR_EXIT / STUB_CACHE_CLEAR_FAIL_ONCE=1   échec de cache:clear (toujours / au 1er appel)
#   STUB_MIGRATIONS_EXIT       code de sortie des migrations
_write_php_stub() {
    cat > "${STUB_BIN}/php" <<STUBEOF
#!/bin/bash
echo "php \$*" >> "${CALL_LOG}"
args="\$*"
if [[ "\$args" == *"--dry-run"* ]]; then
    if [[ "\${STUB_VENDOR_INCOMPLETE:-0}" == "1" || ( "\${STUB_VENDOR_INCOMPLETE:-0}" == "once" && \$(grep -c -e "--dry-run" "${CALL_LOG}") -eq 1 ) ]]; then
        echo "Warning: Composer should be invoked via the CLI version of PHP, not the cgi-fcgi SAPI"
        echo "Usage: composer [options] command [arguments]"
    else
        echo "Nothing to install, update or remove"
    fi
    exit 0
fi
if [[ "\$args" == *"composer.phar install"* ]]; then exit \${STUB_COMPOSER_INSTALL_EXIT:-0}; fi
if [[ "\$args" == *"cache:clear"* ]]; then
    if [[ "\${STUB_CACHE_CLEAR_FAIL_ONCE:-0}" == "1" && \$(grep -c "cache:clear" "${CALL_LOG}") -eq 1 ]]; then exit 1; fi
    exit \${STUB_CACHE_CLEAR_EXIT:-0}
fi
if [[ "\$args" == *"doctrine:migrations:migrate"* ]]; then exit \${STUB_MIGRATIONS_EXIT:-0}; fi
exit 0
STUBEOF
    chmod +x "${STUB_BIN}/php"
}

_write_all_success_stubs() {
    local remote_sha="$1"
    _write_git_stub "$remote_sha"
    _write_passthrough_stub bash
    _write_php_stub
}

# Délai de préavis à 0 par défaut dans les tests (sinon chaque test attendrait
# réellement DEPLOY_NIGHTLY_WARNING_SECONDS) — un test dédié le positionne
# explicitement pour vérifier le sleep lui-même.
export DEPLOY_NIGHTLY_WARNING_SECONDS=0

test_instance_deja_a_jour_ne_deploie_rien() {
    _setup_fixture
    echo "abc1234" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "abc1234"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "0" "$exit_code" "sortie attendue à 0 sur instance déjà à jour"
    assert_file_not_exists "${FIXTURE_DIR}/composer_called"
    local composer_calls
    composer_calls=$(grep -c "composer.phar install" "$CALL_LOG" 2>/dev/null); composer_calls=${composer_calls:-0}
    assert_equals "0" "$composer_calls" "composer ne doit pas être appelé si rien à déployer"
    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|skipped"

    _teardown_fixture
}

test_sha_absent_declenche_un_deploiement() {
    _setup_fixture
    # pas de .deployed-sha : première exécution
    _write_all_success_stubs "def5678"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_contains "$(cat "$CALL_LOG")" "git checkout"
    assert_contains "$(cat "$CALL_LOG")" "composer"
    assert_equals "def5678" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"
    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|ok"

    _teardown_fixture
}

test_sha_different_execute_les_etapes_dans_lordre() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    local calls
    calls="$(cat "$CALL_LOG")"
    local checkout_line composer_line cache_line migrations_line
    checkout_line=$(grep -n "git checkout" <<< "$calls" | head -1 | cut -d: -f1)
    composer_line=$(grep -n "composer.phar install" <<< "$calls" | head -1 | cut -d: -f1)

    if [[ -z "$checkout_line" || -z "$composer_line" ]]; then
        fail "étapes attendues absentes de l'historique d'appels"
    elif [[ "$checkout_line" -ge "$composer_line" ]]; then
        fail "checkout doit précéder composer install (checkout=${checkout_line}, composer=${composer_line})"
    fi

    _teardown_fixture
}

test_echec_conserve_le_sha_precedent_et_stoppe_la_chaine() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_git_stub "new1111"
    _write_passthrough_stub bash
    _write_php_stub
    export STUB_COMPOSER_INSTALL_EXIT=1   # composer install échoue

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "1" "$exit_code" "sortie attendue à 1 sur échec"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"
    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|failed|composer install"

    # Aucune étape après composer install ne doit avoir été appelée
    local console_calls
    console_calls=$(grep -c "bin/console" "$CALL_LOG" 2>/dev/null); console_calls=${console_calls:-0}
    assert_equals "0" "$console_calls" "les étapes après l'échec ne doivent pas s'exécuter"

    _teardown_fixture
}

test_checkout_cible_le_sha_distant_pas_main() {
    _setup_fixture
    _write_all_success_stubs "specific-sha-999"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_contains "$(cat "$CALL_LOG")" "git checkout --force specific-sha-999"

    _teardown_fixture
}

# ── Renoncement silencieux si activité récente (#422 étape 2/3) ─────────────

test_activite_recente_reporte_le_deploiement() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    # Activité il y a 5 minutes (< seuil 15 min) : timestamp Unix brut, comme
    # écrit par App\Service\ActivityTracker::recordActivity().
    echo "$(($(date +%s) - 300))" > "${FIXTURE_DIR}/instance/var/last-activity.txt"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "0" "$exit_code" "sortie attendue à 0 sur report d'activité"
    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|postponed"
    local composer_calls
    composer_calls=$(grep -c "composer.phar install" "$CALL_LOG" 2>/dev/null); composer_calls=${composer_calls:-0}
    assert_equals "0" "$composer_calls" "composer ne doit pas être appelé si activité récente détectée"
    assert_equals "" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha" 2>/dev/null)" ".deployed-sha ne doit pas changer sur un report"

    _teardown_fixture
}

test_activite_ancienne_ne_bloque_pas_le_deploiement() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    # Activité il y a 20 minutes (> seuil 15 min) : le déploiement doit se
    # dérouler normalement.
    echo "$(($(date +%s) - 1200))" > "${FIXTURE_DIR}/instance/var/last-activity.txt"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|ok"
    assert_contains "$(cat "$CALL_LOG")" "git checkout"

    _teardown_fixture
}

test_absence_de_fichier_activite_ne_bloque_pas_le_deploiement() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    # Pas de var/last-activity.txt : aucune activité tracée, le déploiement
    # doit se dérouler normalement (comportement par défaut, cas majoritaire
    # sur une instance jamais visitée récemment).

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|ok"

    _teardown_fixture
}

# ── Signal de préavis avant déploiement (#422 étape 3/3) ─────────────────────

test_ecrit_le_signal_de_preavis_avant_le_sleep() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    # Signal vérifié DEPUIS le stub "sleep" (donc pendant le préavis, avant
    # qu'il soit retiré en fin de script) — vérifier après coup serait faux
    # positif, le fichier étant retiré une fois le déploiement terminé.
    local signal_seen_during_sleep="${FIXTURE_DIR}/signal_seen"
    cat > "${STUB_BIN}/sleep" <<EOF
#!/bin/bash
[[ -f "${FIXTURE_DIR}/instance/var/deploy-imminent.txt" ]] && touch "${signal_seen_during_sleep}"
EOF
    chmod +x "${STUB_BIN}/sleep"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_file_exists "$signal_seen_during_sleep"

    _teardown_fixture
}

test_signal_de_preavis_est_retire_apres_deploiement() {
    _setup_fixture
    _write_all_success_stubs "new1111"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_file_not_exists "${FIXTURE_DIR}/instance/var/deploy-imminent.txt"

    _teardown_fixture
}

test_pas_de_signal_si_deploiement_deja_a_jour() {
    _setup_fixture
    echo "abc1234" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "abc1234"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_file_not_exists "${FIXTURE_DIR}/instance/var/deploy-imminent.txt"

    _teardown_fixture
}

test_pas_de_signal_si_activite_recente_immediate() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    echo "$(($(date +%s) - 300))" > "${FIXTURE_DIR}/instance/var/last-activity.txt"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_file_not_exists "${FIXTURE_DIR}/instance/var/deploy-imminent.txt"

    _teardown_fixture
}

test_reverifie_activite_apres_le_sleep_et_reporte_si_reconnexion() {
    _setup_fixture
    _write_all_success_stubs "new1111"
    # Pas d'activité au moment du lancement (sinon le signal ne serait même
    # pas écrit), mais le script simule une reconnexion PENDANT le sleep en
    # écrivant last-activity.txt via un stub "sleep" qui s'exécute à sa place.
    local activity_file="${FIXTURE_DIR}/instance/var/last-activity.txt"
    cat > "${STUB_BIN}/sleep" <<EOF
#!/bin/bash
echo "sleep \$*" >> "${CALL_LOG}"
echo "\$(date +%s)" > "${activity_file}"
EOF
    chmod +x "${STUB_BIN}/sleep"
    DEPLOY_NIGHTLY_WARNING_SECONDS=1 /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1

    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|postponed"
    local composer_calls
    composer_calls=$(grep -c "composer.phar install" "$CALL_LOG" 2>/dev/null); composer_calls=${composer_calls:-0}
    assert_equals "0" "$composer_calls" "composer ne doit pas être appelé si reconnexion pendant le préavis"

    _teardown_fixture
}

# ── Composer via PHP CLI explicite, jamais « nu » (#570) ────────────────────
# Incident du 2026-10-02 : composer lancé via le PHP CGI du PATH cron affichait
# son aide et sortait en 0 ; vendor/ restait incomplet → 6 instances en 500.

_report() { cat "${FIXTURE_DIR}/report.log" 2>/dev/null; }
_count_calls() { local n; n=$(grep -c -e "$1" "$CALL_LOG" 2>/dev/null); echo "${n:-0}"; }
_run_nightly() {
    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
}

test_composer_est_lance_via_php_cli_explicite() {
    _setup_fixture
    _write_all_success_stubs "new1111"

    _run_nightly

    assert_contains "$(cat "$CALL_LOG")" "composer.phar install --no-interaction"
    assert_equals "0" "$(_count_calls '^composer ')" "aucun appel direct à « composer »"

    _teardown_fixture
}

test_vendor_incomplet_apres_composer_fait_echouer_et_restaure() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"
    export STUB_HEAD_SHA="old0000" STUB_VENDOR_INCOMPLETE="once"

    _run_nightly
    local exit_code=$?

    assert_equals "1" "$exit_code" "composer qui n'installe rien ne doit pas passer pour un succès"
    assert_contains "$(_report)" "yannick|failed|vérification de vendor/"
    assert_contains "$(_report)" "code restauré (old0000)"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"
    # Seul le cache:clear de la restauration a tourné : celui du déploiement
    # n'a jamais été atteint.
    assert_equals "1" "$(_count_calls 'cache:clear')" "cache:clear du déploiement ne doit pas être atteint"

    _teardown_fixture
}

# ── Rollback (#570) ─────────────────────────────────────────────────────────

test_echec_avant_migrations_restaure_le_code_precedent() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"
    export STUB_HEAD_SHA="old0000" STUB_CACHE_CLEAR_FAIL_ONCE=1

    _run_nightly
    local exit_code=$?

    assert_equals "1" "$exit_code"
    local calls checkout_new checkout_old
    calls="$(cat "$CALL_LOG")"
    checkout_new=$(grep -n "git checkout --force new1111" <<< "$calls" | head -1 | cut -d: -f1)
    checkout_old=$(grep -n "git checkout --force old0000" <<< "$calls" | head -1 | cut -d: -f1)
    if [[ -z "$checkout_new" || -z "$checkout_old" || "$checkout_old" -le "$checkout_new" ]]; then
        fail "le code précédent doit être restauré après le checkout du nouveau (new=${checkout_new}, old=${checkout_old})"
    fi
    assert_equals "2" "$(_count_calls 'composer.phar install --no-interaction')" "vendor/ réinstallé pour l'ancien lock"
    assert_equals "2" "$(_count_calls 'cache:clear')" "cache vidé à nouveau après restauration"
    assert_contains "$(_report)" "yannick|failed|cache:clear"
    assert_contains "$(_report)" "code restauré (old0000)"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"

    _teardown_fixture
}

test_rollback_en_echec_est_signale_sans_ambiguite() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"
    export STUB_HEAD_SHA="old0000" STUB_CACHE_CLEAR_EXIT=1

    _run_nightly
    local exit_code=$?

    assert_equals "1" "$exit_code"
    assert_contains "$(_report)" "ROLLBACK ÉCHOUÉ"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"

    _teardown_fixture
}

# Migrations en échec : l'état de la base est incertain (migration partielle),
# restaurer l'ancien code sur un schéma à moitié migré serait pire.
test_echec_des_migrations_ne_restaure_pas_le_code() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"
    export STUB_HEAD_SHA="old0000" STUB_MIGRATIONS_EXIT=1

    _run_nightly
    local exit_code=$?

    assert_equals "1" "$exit_code"
    assert_equals "1" "$(_count_calls 'git checkout --force')" "pas de restauration du code après un échec de migration"
    assert_contains "$(_report)" "yannick|failed|migrations"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"

    _teardown_fixture
}

# HEAD déjà sur la cible (cas de l'incident : code nouveau, vendor ancien, lors
# d'une nouvelle tentative) : rien à restaurer, un second checkout n'aiderait pas.
test_pas_de_rollback_si_le_code_en_place_est_deja_la_cible() {
    _setup_fixture
    echo "old0000" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "new1111"
    export STUB_HEAD_SHA="new1111" STUB_CACHE_CLEAR_EXIT=1

    _run_nightly
    local exit_code=$?

    assert_equals "1" "$exit_code"
    assert_equals "1" "$(_count_calls 'git checkout --force')"
    assert_equals "0" "$(grep -c 'ROLLBACK\|restauré' "${FIXTURE_DIR}/report.log")" "aucune mention de rollback"

    _teardown_fixture
}
