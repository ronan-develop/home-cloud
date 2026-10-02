#!/usr/bin/env bash
# =============================================================================
# Tests de bin/rotate-logs.sh (#606) — rotation des logs applicatifs.
# Constat 2026-10-02 : var/log/messenger.log atteignait ~50 Mo sur les 7
# instances (le worker Messenger réimprime sa bannière à chaque démarrage,
# cron toutes les minutes), sans aucune purge.
# =============================================================================

ROTATE_SCRIPT="${PROJECT_ROOT}/bin/rotate-logs.sh"

_rotate_setup() {
    ROT_DIR="$(mktemp -d)"
    export ROTATE_LOGS_MAX_BYTES=100 ROTATE_LOGS_KEEP=3
}

_rotate_teardown() {
    rm -rf "$ROT_DIR"
    unset ROTATE_LOGS_MAX_BYTES ROTATE_LOGS_KEEP
    hash -r
}

_big_content() { printf 'ligne-%s-' "$1"; head -c 200 /dev/zero | tr '\0' 'x'; echo; }

test_log_sous_le_seuil_est_intact() {
    _rotate_setup
    echo "petit" > "${ROT_DIR}/messenger.log"

    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1

    assert_equals "petit" "$(cat "${ROT_DIR}/messenger.log")"
    assert_file_not_exists "${ROT_DIR}/messenger.log.1.gz"

    _rotate_teardown
}

test_log_au_dessus_du_seuil_est_archive_puis_vide() {
    _rotate_setup
    _big_content A > "${ROT_DIR}/messenger.log"
    local before
    before="$(cat "${ROT_DIR}/messenger.log")"

    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "0" "$exit_code"
    assert_file_exists "${ROT_DIR}/messenger.log.1.gz"
    assert_equals "$before" "$(gunzip -c "${ROT_DIR}/messenger.log.1.gz")" "l'archive contient exactement l'ancien contenu"
    assert_equals "0" "$(wc -c < "${ROT_DIR}/messenger.log" | tr -d ' ')" "le log est vidé"

    _rotate_teardown
}

test_les_archives_sont_decalees_et_limitees_a_keep() {
    _rotate_setup
    echo "ancienne-1" | gzip > "${ROT_DIR}/messenger.log.1.gz"
    echo "ancienne-2" | gzip > "${ROT_DIR}/messenger.log.2.gz"
    echo "ancienne-3" | gzip > "${ROT_DIR}/messenger.log.3.gz"
    _big_content NEW > "${ROT_DIR}/messenger.log"

    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1

    assert_contains "$(gunzip -c "${ROT_DIR}/messenger.log.1.gz")" "ligne-NEW"
    assert_equals "ancienne-1" "$(gunzip -c "${ROT_DIR}/messenger.log.2.gz")"
    assert_equals "ancienne-2" "$(gunzip -c "${ROT_DIR}/messenger.log.3.gz")"
    assert_file_not_exists "${ROT_DIR}/messenger.log.4.gz"

    _rotate_teardown
}

test_seuls_les_fichiers_log_sont_traites() {
    _rotate_setup
    _big_content X > "${ROT_DIR}/notes.txt"
    _big_content Y > "${ROT_DIR}/messenger.log"

    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1

    assert_contains "$(cat "${ROT_DIR}/notes.txt")" "ligne-X"
    assert_file_not_exists "${ROT_DIR}/notes.txt.1.gz"
    assert_file_exists "${ROT_DIR}/messenger.log.1.gz"

    _rotate_teardown
}

# Le worker Messenger écrit via « >> messenger.log » et garde son descripteur
# ouvert : la rotation doit tronquer, pas renommer, et les écritures suivantes
# doivent repartir du début (O_APPEND), sans trou ni octets nuls.
test_ecritures_du_worker_apres_rotation_repartent_de_zero() {
    _rotate_setup
    _big_content W > "${ROT_DIR}/messenger.log"
    exec 9>>"${ROT_DIR}/messenger.log"

    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1
    echo "apres-rotation" >&9
    exec 9>&-

    assert_equals "apres-rotation" "$(cat "${ROT_DIR}/messenger.log")" "uniquement les nouvelles lignes"
    assert_equals "15" "$(wc -c < "${ROT_DIR}/messenger.log" | tr -d ' ')" "pas de trou (octets nuls) en tête de fichier"

    _rotate_teardown
}

# Si l'archivage échoue, on ne tronque JAMAIS : perdre des logs pour rien serait pire.
test_echec_de_gzip_laisse_le_log_intact_et_ne_fait_pas_echouer() {
    _rotate_setup
    local stubs
    stubs="$(mktemp -d)"
    printf '#!/bin/bash\nexit 1\n' > "${stubs}/gzip"
    chmod +x "${stubs}/gzip"
    _big_content F > "${ROT_DIR}/messenger.log"
    local before
    before="$(cat "${ROT_DIR}/messenger.log")"

    PATH="${stubs}:${PATH}" bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1
    local exit_code=$?

    assert_equals "0" "$exit_code" "la rotation ne doit jamais faire échouer l'appelant"
    assert_equals "$before" "$(cat "${ROT_DIR}/messenger.log")" "log intact si l'archivage échoue"

    rm -rf "$stubs"
    _rotate_teardown
}

test_dossier_absent_ou_vide_ne_fait_pas_echouer() {
    _rotate_setup

    bash "$ROTATE_SCRIPT" "${ROT_DIR}/inexistant" > /dev/null 2>&1
    assert_equals "0" "$?" "dossier absent"
    bash "$ROTATE_SCRIPT" "$ROT_DIR" > /dev/null 2>&1
    assert_equals "0" "$?" "dossier vide"

    _rotate_teardown
}
