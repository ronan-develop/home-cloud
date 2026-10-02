#!/usr/bin/env bash
# =============================================================================
# Garde-fou transversal aux scripts de déploiement (#570) : composer ne doit
# jamais être appelé « nu ». Incident du 2026-10-02 : lancé via le PHP CGI du
# PATH cron, composer affichait son aide et sortait en 0 sans rien installer.
# Le correctif de la veille n'avait été appliqué qu'à un des trois scripts.
# =============================================================================

test_aucun_composer_nu_dans_les_scripts_de_deploiement() {
    local bare
    bare=$(grep -nE 'COMPOSER_BIN="composer"|(&&|;)[[:space:]]*composer[[:space:]]+install' \
        "${PROJECT_ROOT}"/bin/*.sh "${PROJECT_ROOT}"/bin/lib/*.sh 2>/dev/null | grep -v '^[^:]*:[0-9]*:[[:space:]]*#')
    assert_equals "" "$bare" "composer ne doit jamais être appelé sans PHP CLI explicite"
}

test_les_trois_scripts_partagent_la_definition_commune() {
    local script
    for script in deploy-nightly.sh deploy-all.sh deploy.sh; do
        assert_contains "$(cat "${PROJECT_ROOT}/bin/${script}")" "lib/deploy-common.sh"
    done
}
