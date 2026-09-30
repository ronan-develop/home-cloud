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

# Chemins absolus obligatoires : un cron cPanel s'exécute avec un PATH minimal
# (pas celui du profil shell interactif) — "composer"/"php" seuls ne résolvent
# à rien et font échouer le déploiement en silence (#421, échec réel constaté
# la nuit du 2026-09-12 : « composer : commande introuvable »).
# -d memory_limit=512M : sur le mutualisé o2switch (LVE CloudLinux), le
# memory_limit par défaut du php.ini fait tuer cache:clear --env=prod même
# isolé dans son propre process SSH (vécu 2026-09-27) — la valeur par défaut
# est trop juste pour la compilation du container Symfony en prod.
PHP_BIN="${DEPLOY_NIGHTLY_PHP_BIN:-/usr/local/bin/php} -d memory_limit=512M"
COMPOSER_BIN="${DEPLOY_NIGHTLY_COMPOSER_BIN:-/usr/local/bin/composer}"

cd "$INSTANCE_PATH" || exit 1
mkdir -p var/log

report_line() {
    # <prenom>|<statut>|<étape ou ->|<sha ou ->
    echo "${PRENOM}|${1}|${2:--}|${3:--}" >> "$REPORT_FILE"
}

# ── Une connexion/sous-shell par étape, comme bin/deploy-all.sh ─────────────
FAILED_STEP=""
# Exit 127 = "commande introuvable" (composer/php absents du PATH cron) : une
# erreur d'environnement qui échouera identiquement chaque nuit — jamais
# résolue par un simple retry, contrairement aux autres échecs (réseau,
# migration transitoire...). Un email disant "aucune action requise" sur ce
# cas précis a fait dormir un vrai incident 2 semaines sans intervention
# (instance damien, 2026-09-12 → 2026-09-27) — d'où le statut "critical"
# dédié, plus honnête, et un ticket GitHub ouvert automatiquement.
COMMAND_NOT_FOUND_EXIT_CODE=127
IS_CRITICAL=false
run_step() {
    local label="$1"
    shift
    local exit_code=0
    ( "$@" ) || exit_code=$?
    if [[ "$exit_code" -ne 0 ]]; then
        echo "✖ ${PRENOM} — échec à l'étape « ${label} »" >&2
        FAILED_STEP="$label"
        if [[ "$exit_code" -eq "$COMMAND_NOT_FOUND_EXIT_CODE" ]]; then
            IS_CRITICAL=true
        fi
        return 1
    fi
    return 0
}

open_critical_ticket() {
    local step="$1"
    if ! command -v gh &>/dev/null; then
        return 0
    fi
    gh issue create \
        --repo ronan-develop/home-cloud \
        --title "Déploiement nocturne critique : ${PRENOM} — commande introuvable à l'étape « ${step} »" \
        --label bug \
        --body "Le déploiement nocturne sur \`${PRENOM}.lenouvel.me\` a échoué à l'étape **${step}** avec une commande introuvable (exit 127). Cette erreur d'environnement (PATH cron minimal, binaire absent/déplacé) ne se résoudra pas seule — chaque nouvelle tentative nocturne échouera identiquement jusqu'à correction manuelle (vérifier \`DEPLOY_NIGHTLY_PHP_BIN\`/\`DEPLOY_NIGHTLY_COMPOSER_BIN\` ou l'installation du binaire sur cette instance)." \
        &>/dev/null || echo "⚠ ${PRENOM} : échec de la création automatique du ticket GitHub" >&2
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
is_activity_recent() {
    if [[ ! -f "$ACTIVITY_FILE" ]]; then
        return 1
    fi
    local last
    last=$(cat "$ACTIVITY_FILE" 2>/dev/null || echo "")
    if [[ ! "$last" =~ ^[0-9]+$ ]]; then
        return 1
    fi
    (( $(date +%s) - last < ACTIVITY_THRESHOLD_SECONDS ))
}

if is_activity_recent; then
    echo "${PRENOM} : activité récente détectée, déploiement reporté à la nuit prochaine."
    report_line "postponed"
    exit 0
fi

# ── Préavis avant déploiement (#422 étape 3/3) ───────────────────────────────
# Signal lu en polling par un endpoint PHP pour avertir un utilisateur qui se
# reconnecterait pendant la fenêtre de préavis — popup avec compte à rebours
# côté front. À l'issue du sleep, l'activité est revérifiée : une
# reconnexion pendant le préavis reporte le déploiement comme une activité
# détectée en amont, plutôt que de couper un upload qui vient de démarrer.
WARNING_SECONDS="${DEPLOY_NIGHTLY_WARNING_SECONDS:-600}"
IMMINENT_FILE="var/deploy-imminent.txt"
date +%s > "$IMMINENT_FILE"
sleep "$WARNING_SECONDS"

if is_activity_recent; then
    rm -f "$IMMINENT_FILE"
    echo "${PRENOM} : reconnexion pendant le préavis, déploiement reporté à la nuit prochaine."
    report_line "postponed"
    exit 0
fi

if run_step "git checkout"       git checkout --force "$REMOTE_SHA" \
&& run_step "composer install"   "$COMPOSER_BIN" install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts \
&& run_step "install-ffmpeg"     bash bin/install-ffmpeg.sh \
&& run_step "cache:clear"        $PHP_BIN bin/console cache:clear --env=prod \
&& run_step "assets:install"     bash -c "umask 022 && $PHP_BIN bin/console assets:install public --env=prod" \
&& run_step "importmap:install"  bash -c "umask 022 && $PHP_BIN bin/console importmap:install --env=prod" \
&& run_step "migrations"         $PHP_BIN bin/console doctrine:migrations:migrate --no-interaction --env=prod \
&& run_step "asset-map:compile"  bash -c "umask 022 && $PHP_BIN bin/console asset-map:compile"; then
    rm -f "$IMMINENT_FILE"
    echo "<!-- Deployed: $(date '+%Y-%m-%d %H:%M:%S') -->" > templates/deploy-info.html.twig
    echo "$REMOTE_SHA" > .deployed-sha
    echo "${PRENOM} : déployé (${REMOTE_SHA:0:7})."
    report_line "ok" "-" "${REMOTE_SHA:0:7}"
    exit 0
else
    rm -f "$IMMINENT_FILE"
    echo "${PRENOM} : échec du déploiement, .deployed-sha inchangé." >&2
    if [[ "$IS_CRITICAL" == true ]]; then
        report_line "critical" "${FAILED_STEP:-inconnue}"
        open_critical_ticket "${FAILED_STEP:-inconnue}"
    else
        report_line "failed" "${FAILED_STEP:-inconnue}"
    fi
    exit 1
fi
