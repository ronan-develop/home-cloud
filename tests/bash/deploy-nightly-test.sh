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
}

_teardown_fixture() {
    rm -rf "$FIXTURE_DIR"
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
        echo "${remote_sha}"
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

_write_all_success_stubs() {
    local remote_sha="$1"
    _write_git_stub "$remote_sha"
    _write_passthrough_stub composer
    _write_passthrough_stub bash
    _write_passthrough_stub php
}

test_instance_deja_a_jour_ne_deploie_rien() {
    _setup_fixture
    echo "abc1234" > "${FIXTURE_DIR}/instance/.deployed-sha"
    _write_all_success_stubs "abc1234"

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "0" "$exit_code" "sortie attendue à 0 sur instance déjà à jour"
    assert_file_not_exists "${FIXTURE_DIR}/composer_called"
    local composer_calls
    composer_calls=$(grep -c "^composer " "$CALL_LOG" 2>/dev/null); composer_calls=${composer_calls:-0}
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
    composer_line=$(grep -n "^composer install" <<< "$calls" | head -1 | cut -d: -f1)

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
    _write_passthrough_stub composer 1   # composer install échoue
    _write_passthrough_stub bash
    _write_passthrough_stub php

    /usr/bin/bash "$DEPLOY_NIGHTLY_SCRIPT" yannick "${FIXTURE_DIR}/instance" "${FIXTURE_DIR}/report.log" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "1" "$exit_code" "sortie attendue à 1 sur échec"
    assert_equals "old0000" "$(cat "${FIXTURE_DIR}/instance/.deployed-sha")"
    assert_contains "$(cat "${FIXTURE_DIR}/report.log")" "yannick|failed|composer install"

    # Aucune étape après composer install ne doit avoir été appelée
    local php_calls
    php_calls=$(grep -c "^php " "$CALL_LOG" 2>/dev/null); php_calls=${php_calls:-0}
    assert_equals "0" "$php_calls" "les étapes après l'échec ne doivent pas s'exécuter"

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
    composer_calls=$(grep -c "^composer " "$CALL_LOG" 2>/dev/null); composer_calls=${composer_calls:-0}
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
