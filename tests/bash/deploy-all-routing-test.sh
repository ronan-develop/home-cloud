#!/usr/bin/env bash
# =============================================================================
# Tests de la logique de ROUTAGE de bin/deploy-all.sh (#421) — sans réseau
# réel : ssh/scp/php/composer mockés. Ne teste PAS le contenu d'un vrai
# déploiement (déjà couvert par bin/deploy-nightly.sh et son historique),
# seulement : qui est déployé immédiatement, qui est différé.
# =============================================================================

_setup_routing_fixture() {
    FIXTURE_DIR="$(mktemp -d)"
    STUB_BIN="${FIXTURE_DIR}/bin"
    mkdir -p "$STUB_BIN"

    CALL_LOG="${FIXTURE_DIR}/calls.log"
    touch "$CALL_LOG"

    # .deploy-targets attendu par le script, à la racine du projet réel —
    # on le pointe vers une copie temporaire via ROOT_DIR n'est pas possible
    # (chemin calculé depuis BASH_SOURCE), donc on écrit/restaure le vrai
    # fichier le temps du test.
    REAL_TARGETS_FILE="${PROJECT_ROOT}/.deploy-targets"
    TARGETS_BACKUP=""
    if [[ -f "$REAL_TARGETS_FILE" ]]; then
        TARGETS_BACKUP="${FIXTURE_DIR}/targets.bak"
        cp "$REAL_TARGETS_FILE" "$TARGETS_BACKUP"
    fi
    printf "ronan\nyannick\ncoralie\n" > "$REAL_TARGETS_FILE"

    export FIXTURE_DIR STUB_BIN CALL_LOG REAL_TARGETS_FILE TARGETS_BACKUP
    export PATH="${STUB_BIN}:${PATH}"
    hash -r
}

_teardown_routing_fixture() {
    if [[ -n "$TARGETS_BACKUP" && -f "$TARGETS_BACKUP" ]]; then
        cp "$TARGETS_BACKUP" "$REAL_TARGETS_FILE"
    else
        rm -f "$REAL_TARGETS_FILE"
    fi
    rm -rf "$FIXTURE_DIR"
    hash -r
}

_write_routing_stubs() {
    # ssh : répond "OK" au test de connexion, "" au REMOTE_HOME, et log tout appel
    cat > "${STUB_BIN}/ssh" <<'EOF'
#!/bin/bash
echo "ssh $*" >> "__CALL_LOG__"
if [[ "$*" == *"echo OK"* ]]; then
    echo "OK"
elif [[ "$*" == *'echo $HOME'* ]]; then
    echo "/home9/ron2cuba"
fi
exit 0
EOF
    sed -i "s|__CALL_LOG__|${CALL_LOG}|" "${STUB_BIN}/ssh"
    chmod +x "${STUB_BIN}/ssh"

    cat > "${STUB_BIN}/scp" <<EOF
#!/bin/bash
echo "scp \$*" >> "${CALL_LOG}"
exit 0
EOF
    chmod +x "${STUB_BIN}/scp"

    cat > "${STUB_BIN}/php" <<EOF
#!/bin/bash
echo "php \$*" >> "${CALL_LOG}"
exit 0
EOF
    chmod +x "${STUB_BIN}/php"
}

test_sans_flag_ronan_immediat_les_autres_differes() {
    _setup_routing_fixture
    _write_routing_stubs

    local output
    output=$(bash "${PROJECT_ROOT}/bin/deploy-all.sh" 2>&1)

    assert_contains "$output" "✅"
    assert_contains "$output" "ronan.lenouvel.me"
    assert_contains "$output" "⏳"
    assert_contains "$output" "yannick.lenouvel.me"
    assert_contains "$output" "coralie.lenouvel.me"
    assert_contains "$output" "différé au déploiement nocturne"

    # yannick/coralie ne doivent PAS avoir reçu de scp (pas de déploiement réel)
    local scp_targets
    scp_targets=$(grep "^scp " "$CALL_LOG" | grep -c "yannick\|coralie" || true)
    assert_equals "0" "${scp_targets:-0}" "aucun scp attendu vers les instances différées"

    _teardown_routing_fixture
}

test_now_mode_deploie_les_trois_immediatement() {
    _setup_routing_fixture
    _write_routing_stubs

    local output
    output=$(DEPLOY_NOW_CONFIRM=URGENT bash "${PROJECT_ROOT}/bin/deploy-all.sh" --now 2>&1)

    assert_contains "$output" "ronan.lenouvel.me"
    assert_contains "$output" "yannick.lenouvel.me"
    assert_contains "$output" "coralie.lenouvel.me"

    # --now : aucune instance ne doit apparaître comme différée
    if [[ "$output" == *"différé au déploiement nocturne"* ]]; then
        fail "aucune instance ne devrait être différée en mode --now"
    fi

    _teardown_routing_fixture
}

test_now_mode_sans_confirmation_annule() {
    _setup_routing_fixture
    _write_routing_stubs

    local output exit_code
    output=$(echo "non" | bash "${PROJECT_ROOT}/bin/deploy-all.sh" --now 2>&1)
    exit_code=$?

    assert_equals "1" "$exit_code" "refus de confirmation doit annuler avec sortie 1"
    assert_contains "$output" "annulé"

    _teardown_routing_fixture
}

test_init_mode_ignore_le_differe() {
    _setup_routing_fixture
    # --init a son propre chemin (clone), pas besoin des stubs scp/php complets
    # pour ce test de routage — on vérifie juste l'ABSENCE de la mention "différé"
    cat > "${STUB_BIN}/ssh" <<EOF
#!/bin/bash
echo "ssh \$*" >> "${CALL_LOG}"
if [[ "\$*" == *"echo OK"* ]]; then echo "OK"; fi
if [[ "\$*" == *'echo \$HOME'* ]]; then echo "/home9/ron2cuba"; fi
exit 1
EOF
    chmod +x "${STUB_BIN}/ssh"

    local output
    output=$(bash "${PROJECT_ROOT}/bin/deploy-all.sh" --init 2>&1 || true)

    if [[ "$output" == *"différé au déploiement nocturne"* ]]; then
        fail "--init ne doit jamais différer une instance"
    fi

    _teardown_routing_fixture
}
