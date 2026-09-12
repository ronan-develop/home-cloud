#!/usr/bin/env bash
# =============================================================================
# HomeCloud — Déploiement nocturne autonome d'une instance (#421)
#
# Usage : bash bin/deploy-nightly.sh <prenom> <chemin_instance> <chemin_rapport>
# Appelé par un cron cPanel, une invocation par instance, étalées de 5 min
# (contrainte LVE partagée entre les 7 instances, cf. incident #395/#396).
#
# Compare le SHA de origin/main au SHA déployé (.deployed-sha) : ne déploie
# que si différent. Chaque étape de déploiement tourne dans son propre
# sous-shell (cf. run_step de bin/deploy-all.sh) pour ne jamais accumuler
# l'empreinte mémoire de plusieurs commandes bin/console dans un seul
# process — cause des `Killed` OOM vécus le 2026-07-23 sur ce même LVE.
# =============================================================================

set -uo pipefail

PRENOM="${1:?Usage: deploy-nightly.sh <prenom> <chemin_instance> <chemin_rapport>}"
INSTANCE_PATH="${2:?chemin_instance manquant}"
REPORT_FILE="${3:?chemin_rapport manquant}"

PHP_BIN="${DEPLOY_NIGHTLY_PHP_BIN:-php}"
COMPOSER_BIN="${DEPLOY_NIGHTLY_COMPOSER_BIN:-composer}"

cd "$INSTANCE_PATH" || exit 1
mkdir -p var/log

report_line() {
    # <prenom>|<statut>|<étape ou ->|<sha ou ->
    echo "${PRENOM}|${1}|${2:--}|${3:--}" >> "$REPORT_FILE"
}

# ── Une connexion/sous-shell par étape, comme bin/deploy-all.sh ─────────────
FAILED_STEP=""
run_step() {
    local label="$1"
    shift
    if ! ( "$@" ); then
        echo "✖ ${PRENOM} — échec à l'étape « ${label} »" >&2
        FAILED_STEP="$label"
        return 1
    fi
    return 0
}

git fetch origin main 2>&1
REMOTE_SHA=$(git rev-parse origin/main 2>/dev/null)
CURRENT_SHA=$(cat .deployed-sha 2>/dev/null || echo "")

if [[ -n "$REMOTE_SHA" && "$REMOTE_SHA" == "$CURRENT_SHA" ]]; then
    echo "${PRENOM} : à jour (${CURRENT_SHA:0:7}), rien à déployer."
    report_line "skipped"
    exit 0
fi

# ── Renoncement silencieux si un utilisateur est actif (#422) ───────────────
# Fichier écrit par App\Service\ActivityTracker::recordActivity() (timestamp
# Unix brut, amorti à 5 min) — un déploiement différé d'une nuit est toujours
# préférable à un upload interrompu en pleine nuit chez un noctambule.
ACTIVITY_THRESHOLD_SECONDS=900
ACTIVITY_FILE="var/last-activity.txt"
if [[ -f "$ACTIVITY_FILE" ]]; then
    LAST_ACTIVITY=$(cat "$ACTIVITY_FILE" 2>/dev/null || echo "")
    if [[ "$LAST_ACTIVITY" =~ ^[0-9]+$ ]]; then
        NOW=$(date +%s)
        if (( NOW - LAST_ACTIVITY < ACTIVITY_THRESHOLD_SECONDS )); then
            echo "${PRENOM} : activité récente détectée, déploiement reporté à la nuit prochaine."
            report_line "postponed"
            exit 0
        fi
    fi
fi

if run_step "git checkout"       git checkout --force "$REMOTE_SHA" \
&& run_step "composer install"   "$COMPOSER_BIN" install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts \
&& run_step "install-ffmpeg"     bash bin/install-ffmpeg.sh \
&& run_step "cache:clear"        "$PHP_BIN" bin/console cache:clear --env=prod \
&& run_step "assets:install"     "$PHP_BIN" bin/console assets:install public --env=prod \
&& run_step "importmap:install"  "$PHP_BIN" bin/console importmap:install --env=prod \
&& run_step "migrations"         "$PHP_BIN" bin/console doctrine:migrations:migrate --no-interaction --env=prod \
&& run_step "asset-map:compile"  "$PHP_BIN" bin/console asset-map:compile; then
    echo "<!-- Deployed: $(date '+%Y-%m-%d %H:%M:%S') -->" > templates/deploy-info.html.twig
    echo "$REMOTE_SHA" > .deployed-sha
    echo "${PRENOM} : déployé (${REMOTE_SHA:0:7})."
    report_line "ok" "-" "${REMOTE_SHA:0:7}"
    exit 0
else
    echo "${PRENOM} : échec du déploiement, .deployed-sha inchangé." >&2
    report_line "failed" "${FAILED_STEP:-inconnue}"
    exit 1
fi
