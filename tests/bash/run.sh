#!/usr/bin/env bash
# =============================================================================
# Runner minimal pour les tests bash du projet — pas de dépendance externe
# (bats non introduit sans validation préalable, cf. plan #421 §8).
#
# Convention : chaque fichier tests/bash/*-test.sh définit des fonctions
# test_* qui échouent via `fail "message"`. Ce runner les découvre et les
# exécute toutes, puis résume.
# =============================================================================
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export DEPLOY_NIGHTLY_SCRIPT="${SCRIPT_DIR}/../../bin/deploy-nightly.sh"

TOTAL=0
FAILED=0
CURRENT_TEST=""

fail() {
    echo "  ✖ ${CURRENT_TEST}: $*"
    FAILED=$((FAILED + 1))
}

assert_equals() {
    local expected="$1" actual="$2" msg="${3:-}"
    if [[ "$expected" != "$actual" ]]; then
        fail "attendu <${expected}>, obtenu <${actual}> ${msg}"
    fi
}

assert_file_exists() {
    [[ -f "$1" ]] || fail "fichier attendu absent : $1"
}

assert_file_not_exists() {
    [[ -f "$1" ]] && fail "fichier ne devrait pas exister : $1"
}

assert_contains() {
    local haystack="$1" needle="$2"
    [[ "$haystack" == *"$needle"* ]] || fail "« ${needle} » absent de : ${haystack}"
}

for test_file in "$SCRIPT_DIR"/*-test.sh; do
    [[ -f "$test_file" ]] || continue
    # shellcheck source=/dev/null
    source "$test_file"

    for fn in $(declare -F | awk '{print $3}' | grep '^test_'); do
        CURRENT_TEST="$(basename "$test_file"):${fn}"
        TOTAL=$((TOTAL + 1))
        BEFORE_FAILED=$FAILED
        "$fn"
        if [[ $FAILED -eq $BEFORE_FAILED ]]; then
            echo "  ✔ ${CURRENT_TEST}"
        fi
        unset -f "$fn"
    done
done

echo ""
echo "Tests: ${TOTAL}, Échecs: ${FAILED}"
[[ $FAILED -eq 0 ]]
