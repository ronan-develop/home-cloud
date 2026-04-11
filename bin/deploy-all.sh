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

mapfile -t TARGETS < <(grep -v '^\s*#' "$TARGETS_FILE" | grep -v '^\s*$' | tr -d '[:space:]')

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

        DB_NAME="${SSH_USER}_${PRENOM}"
        DB_USER="${SSH_USER}_${PRENOM}"
        DB_PASSWORD="$DB_PASSWORD_PRESET"
        APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
        JWT_PASSPHRASE=$(php -r "echo bin2hex(random_bytes(24));")
        DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}?serverVersion=mariadb-10.6.0&charset=utf8mb4"

        info "Clonage et setup initial…"
        if ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "
            set -e
            mkdir -p ${DEPLOY_PATH}
            cd ${DEPLOY_PATH}
            git clone ${GIT_REPO} .
            ${COMPOSER_BIN} install --no-interaction --prefer-dist --no-progress --no-dev
            cat > .env.local <<'ENVEOF'
APP_ENV=prod
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_PASSPHRASE}
ENVEOF
            ${PHP_BIN} bin/console cache:clear --env=prod
            ${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --env=prod
            ${PHP_BIN} bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod
            ${PHP_BIN} bin/console asset-map:compile
            echo '<!-- Deployed: '$(date '+%Y-%m-%d %H:%M:%S')' -->' > templates/deploy-info.html.twig
        "; then
            success "${SUBDOMAIN} — déploiement initial OK"
            RESULTS_OK+=("$SUBDOMAIN")
        else
            error "${SUBDOMAIN} — échec"
            RESULTS_FAIL+=("$SUBDOMAIN")
        fi

        # Reset pour ne pas polluer l'instance suivante
        unset DB_PASSWORD_PRESET DB_PASSWORD APP_SECRET JWT_PASSPHRASE DATABASE_URL

    else
        # ── Mise à jour ───────────────────────────────────────────────────────
        info "git pull + composer + cache + migrations + assets…"
        if ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "
            set -e
            cd ${DEPLOY_PATH}
            git pull origin ${GIT_BRANCH}
            ${COMPOSER_BIN} install --no-interaction --prefer-dist --no-progress --no-dev
            ${PHP_BIN} bin/console cache:clear --env=prod
            ${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --env=prod
            ${PHP_BIN} bin/console asset-map:compile
            echo '<!-- Deployed: '$(date '+%Y-%m-%d %H:%M:%S')' -->' > templates/deploy-info.html.twig
        "; then
            success "${SUBDOMAIN} — mise à jour OK"
            RESULTS_OK+=("$SUBDOMAIN")
        else
            error "${SUBDOMAIN} — échec"
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
