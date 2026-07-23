#!/usr/bin/env bash
# =============================================================================
# HomeCloud — Déploiement multi-instances sur o2switch
# Usage :
#   bash bin/deploy-all.sh           → Mise à jour de tous les targets (.deploy-targets)
#   bash bin/deploy-all.sh --init    → Premier déploiement de tous les targets
# =============================================================================

set -euo pipefail

# ── Chargement des secrets globaux ────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="${SCRIPT_DIR}/.."
SECRETS_FILE="${ROOT_DIR}/.secrets"
if [[ -f "$SECRETS_FILE" ]]; then
    # shellcheck source=../.secrets
    source "$SECRETS_FILE"
fi

# ── Mode ──────────────────────────────────────────────────────────────────────
INIT_MODE=false
if [[ "${1:-}" == "--init" ]]; then
    INIT_MODE=true
fi

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${BLUE}ℹ${NC}  $*"; }
success() { echo -e "${GREEN}✔${NC}  $*"; }
warn()    { echo -e "${YELLOW}⚠${NC}  $*"; }
error()   { echo -e "${RED}✖${NC}  $*" >&2; }
title()   { echo -e "\n${BOLD}$*${NC}"; }

# ── Configuration fixe ────────────────────────────────────────────────────────
SSH_USER="ron2cuba"
SSH_HOST="lenouvel.me"
SSH_PORT=22
GIT_REPO="https://github.com/ronan-develop/home-cloud"
GIT_BRANCH="main"
PHP_BIN="/usr/local/bin/php"
COMPOSER_BIN="composer"

SSH_KEY_OPTS=""
if [[ -n "${SSH_KEY_PATH:-}" && -f "${SSH_KEY_PATH}" ]]; then
    SSH_KEY_OPTS="-i ${SSH_KEY_PATH}"
fi

# ── Lecture de .deploy-targets ────────────────────────────────────────────────
TARGETS_FILE="${ROOT_DIR}/.deploy-targets"
if [[ ! -f "$TARGETS_FILE" ]]; then
    error "Fichier .deploy-targets introuvable (${TARGETS_FILE})"
    error "Crée-le avec un prénom par ligne. Exemple :"
    error "  ronan"
    error "  alice"
    exit 1
fi

mapfile -t TARGETS < <(grep -v '^\s*#' "$TARGETS_FILE" | grep -v '^\s*$' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

if [[ ${#TARGETS[@]} -eq 0 ]]; then
    error "Aucune cible trouvée dans .deploy-targets"
    exit 1
fi

# ── Récapitulatif ─────────────────────────────────────────────────────────────
title "═══════════════════════════════════════════════"
if [[ "$INIT_MODE" == true ]]; then
    title "  HomeCloud — Déploiement initial (${#TARGETS[@]} instance(s))"
else
    title "  HomeCloud — Mise à jour (${#TARGETS[@]} instance(s))"
fi
title "═══════════════════════════════════════════════"
echo ""
info "Cibles :"
for t in "${TARGETS[@]}"; do
    echo "    • ${t}.lenouvel.me"
done
echo ""

# ── Vérification SSH globale ──────────────────────────────────────────────────
info "Test de connexion SSH vers ${SSH_USER}@${SSH_HOST}…"
if ! ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" -o ConnectTimeout=10 "${SSH_USER}@${SSH_HOST}" "echo OK" &>/dev/null; then
    error "Connexion SSH impossible."
    error "Vérifiez que votre IP est whitelistée : cPanel → Sécurité → Accès SSH → Autorisation SSH"
    exit 1
fi
success "Connexion SSH OK"

REMOTE_HOME=$(ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" 'echo $HOME')

# ── Suivi des résultats ───────────────────────────────────────────────────────
declare -a RESULTS_OK=()
declare -a RESULTS_FAIL=()

# ── Exécution d'une étape distante dans son propre process SSH ───────────────
# Chaque étape ouvre une connexion SSH neuve (donc un process shell neuf côté
# serveur) au lieu d'enchaîner toutes les commandes dans un seul process —
# le compte tourne dans un LVE CloudLinux (quota mémoire par compte, isolé de
# la RAM totale de la machine) et un process qui accumule cache/opcache sur
# plusieurs commandes bin/console à la suite peut dépasser ce quota, même s'il
# reste beaucoup de RAM libre au niveau système (vécu 2026-07-23 : migrations
# puis cache:clear tués (Killed) sur des instances différentes lors de deux
# déploiements successifs).
run_step() {
    local label="$1"
    local remote_cmd="$2"
    if ! ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "set -e; cd ${DEPLOY_PATH}; ${remote_cmd}"; then
        error "${SUBDOMAIN} — échec à l'étape « ${label} »"
        return 1
    fi
    return 0
}

# ── Boucle sur les cibles ─────────────────────────────────────────────────────
for PRENOM in "${TARGETS[@]}"; do
    SUBDOMAIN="${PRENOM}.lenouvel.me"
    DEPLOY_PATH="${REMOTE_HOME}/${SUBDOMAIN}"

    title "── ${SUBDOMAIN} ──────────────────────────────────"

    if [[ "$INIT_MODE" == true ]]; then
        # ── Chargement des secrets par instance ───────────────────────────────
        INSTANCE_SECRETS="${ROOT_DIR}/.secrets.${PRENOM}"
        if [[ ! -f "$INSTANCE_SECRETS" ]]; then
            error "Fichier .secrets.${PRENOM} introuvable — instance ignorée"
            error "Crée ${INSTANCE_SECRETS} avec DB_PASSWORD_PRESET=<motdepasse>"
            RESULTS_FAIL+=("$SUBDOMAIN (secrets manquants)")
            continue
        fi
        # shellcheck source=../.secrets.ronan
        source "$INSTANCE_SECRETS"

        if [[ -z "${DB_PASSWORD_PRESET:-}" ]]; then
            error "DB_PASSWORD_PRESET absent dans .secrets.${PRENOM} — instance ignorée"
            RESULTS_FAIL+=("$SUBDOMAIN (DB_PASSWORD_PRESET manquant)")
            continue
        fi
        if [[ -z "${MAILER_DSN_PRESET:-}" ]]; then
            warn "MAILER_DSN_PRESET absent (.secrets ou .secrets.${PRENOM}) — l'instance n'enverra aucun email"
        fi

        DB_NAME="${SSH_USER}_${PRENOM}"
        DB_USER="${SSH_USER}_${PRENOM}"
        DB_PASSWORD="$DB_PASSWORD_PRESET"
        APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
        JWT_PASSPHRASE=$(php -r "echo bin2hex(random_bytes(24));")
        DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}?serverVersion=mariadb-10.6.0&charset=utf8mb4"

        info "Clonage et setup initial (étapes séparées)…"
        DEPLOY_INFO_LINE="<!-- Deployed: $(date '+%Y-%m-%d %H:%M:%S') -->"
        ENV_LOCAL_CONTENT="APP_ENV=prod
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_PASSPHRASE}
MAILER_DSN=${MAILER_DSN_PRESET:-null://null}"

        if ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "set -e; mkdir -p ${DEPLOY_PATH}; cd ${DEPLOY_PATH}; git clone ${GIT_REPO} . && mkdir -p var/log && cat > .env.local <<'ENVEOF'
${ENV_LOCAL_CONTENT}
ENVEOF
"; then :; else
            error "${SUBDOMAIN} — échec à l'étape « clone + .env.local »"
            RESULTS_FAIL+=("$SUBDOMAIN")
            unset DB_PASSWORD_PRESET DB_PASSWORD APP_SECRET JWT_PASSPHRASE DATABASE_URL
            continue
        fi

        if run_step "composer install"  "${COMPOSER_BIN} install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts" \
        && run_step "install-ffmpeg"    "bash bin/install-ffmpeg.sh || echo '⚠ ffmpeg non installé — vignettes vidéo indisponibles'" \
        && run_step "cache:clear"       "${PHP_BIN} bin/console cache:clear --env=prod" \
        && run_step "assets:install"    "${PHP_BIN} bin/console assets:install public --env=prod" \
        && run_step "importmap:install" "${PHP_BIN} bin/console importmap:install --env=prod" \
        && run_step "migrations"        "${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --env=prod" \
        && run_step "jwt:generate-keypair" "${PHP_BIN} bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod" \
        && run_step "asset-map:compile" "${PHP_BIN} bin/console asset-map:compile" \
        && run_step "deploy-info"       "echo '${DEPLOY_INFO_LINE}' > templates/deploy-info.html.twig"; then
            success "${SUBDOMAIN} — déploiement initial OK"
            RESULTS_OK+=("$SUBDOMAIN")
        else
            RESULTS_FAIL+=("$SUBDOMAIN")
        fi

        # Reset pour ne pas polluer l'instance suivante
        unset DB_PASSWORD_PRESET DB_PASSWORD APP_SECRET JWT_PASSPHRASE DATABASE_URL

    else
        # ── Mise à jour ───────────────────────────────────────────────────────
        # Tailwind build en local puis scp : le binaire natif ne tourne pas de
        # façon fiable sur o2switch (mutualisé), cf. bin/deploy.sh.
        info "Build Tailwind (local)…"
        php bin/console tailwind:build --minify

        info "Envoi de app.built.css…"
        ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "mkdir -p ${DEPLOY_PATH}/var/tailwind"
        scp ${SSH_KEY_OPTS} -P "${SSH_PORT}" \
            var/tailwind/app.built.css \
            "${SSH_USER}@${SSH_HOST}:${DEPLOY_PATH}/var/tailwind/app.built.css"

        info "git pull + composer + cache + migrations + assets (étapes séparées)…"
        DEPLOY_INFO_LINE="<!-- Deployed: $(date '+%Y-%m-%d %H:%M:%S') -->"
        if run_step "git pull"          "mkdir -p var/log && git pull origin ${GIT_BRANCH}" \
        && run_step "composer install"  "${COMPOSER_BIN} install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts" \
        && run_step "install-ffmpeg"    "bash bin/install-ffmpeg.sh || echo '⚠ ffmpeg non installé — vignettes vidéo indisponibles'" \
        && run_step "cache:clear"       "${PHP_BIN} bin/console cache:clear --env=prod" \
        && run_step "assets:install"    "${PHP_BIN} bin/console assets:install public --env=prod" \
        && run_step "importmap:install" "${PHP_BIN} bin/console importmap:install --env=prod" \
        && run_step "migrations"        "${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --env=prod" \
        && run_step "asset-map:compile" "${PHP_BIN} bin/console asset-map:compile" \
        && run_step "deploy-info"       "echo '${DEPLOY_INFO_LINE}' > templates/deploy-info.html.twig"; then
            success "${SUBDOMAIN} — mise à jour OK"
            RESULTS_OK+=("$SUBDOMAIN")
        else
            RESULTS_FAIL+=("$SUBDOMAIN")
        fi
    fi
done

# ── Récapitulatif final ───────────────────────────────────────────────────────
title "═══════════════════════════════════════════════"
title "  Résultat"
title "═══════════════════════════════════════════════"
for d in "${RESULTS_OK[@]:-}"; do
    [[ -n "$d" ]] && echo -e "  ${GREEN}✅${NC}  ${d}"
done
for d in "${RESULTS_FAIL[@]:-}"; do
    [[ -n "$d" ]] && echo -e "  ${RED}❌${NC}  ${d}"
done
echo ""

if [[ ${#RESULTS_FAIL[@]} -gt 0 ]]; then
    exit 1
fi
