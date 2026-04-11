#!/usr/bin/env bash
# =============================================================================
# HomeCloud — Script de déploiement sur o2switch
# Usage :
#   bash bin/deploy.sh           → Premier déploiement (setup complet)
#   bash bin/deploy.sh --update  → Mise à jour du code uniquement
# =============================================================================

# ── Chargement des secrets locaux (non versionnés) ────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SECRETS_FILE="${SCRIPT_DIR}/../.secrets"
if [[ -f "$SECRETS_FILE" ]]; then
    # shellcheck source=../.secrets
    source "$SECRETS_FILE"
fi

set -euo pipefail

# ── Mode : déploiement initial ou mise à jour ─────────────────────────────────
UPDATE_MODE=false
if [[ "${1:-}" == "--update" ]]; then
    UPDATE_MODE=true
fi

# ── Couleurs ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# ── Vérification pré-déploiement ──────────────────────────────────────────────
echo -e "${YELLOW}⚠️  Avant de lancer ce script, vérifie :${NC}"
echo -e "  1. La Pull Request est bien mergée sur main sur GitHub."
echo -e "  2. Tu es bien sur la branche main (git checkout main)."
echo -e "  3. Le push sur origin/main est fait (git push origin main)."
echo -e "${BLUE}Astuce : Tu peux vérifier la branche avec 'git branch --show-current'${NC}"
echo ""

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

# Options SSH : utilise la clé dédiée si définie dans .secrets
SSH_KEY_OPTS=""
if [[ -n "${SSH_KEY_PATH:-}" && -f "$SSH_KEY_PATH" ]]; then
    SSH_KEY_OPTS="-i ${SSH_KEY_PATH}"
fi
GIT_BRANCH="main"
PHP_BIN="/usr/local/bin/php"
COMPOSER_BIN="composer"

# ── Questionnaire ─────────────────────────────────────────────────────────────
title "═══════════════════════════════════════"
if [[ "$UPDATE_MODE" == true ]]; then
    title "  HomeCloud — Mise à jour o2switch"
else
    title "  HomeCloud — Déploiement o2switch"
fi
title "═══════════════════════════════════════"
echo ""
echo -e "${YELLOW}  Prérequis avant de continuer :${NC}"
echo "  1. Votre IP est whitelistée dans cPanel → Outils → 'Autorisation SSH'"
echo "     Voir votre IP : https://mon-ip.io"
echo "  2. Vous avez accès SSH : ssh ${SSH_USER}@${SSH_HOST}"
echo ""
if [[ -n "${PREREQ_PRESET:-}" ]]; then PREREQ="$PREREQ_PRESET"; else read -rp "$(echo -e "${BOLD}Les prérequis SSH sont remplis ? [o/N] :${NC} ")" PREREQ; fi
if [[ "$PREREQ" != "o" && "$PREREQ" != "O" ]]; then
    warn "Whitelistez votre IP dans cPanel → Outils → 'Autorisation SSH' puis relancez."
    exit 0
fi

echo ""
if [[ -n "${PRENOM_PRESET:-}" ]]; then
    PRENOM="$PRENOM_PRESET"
    success "Prénom chargé depuis PRENOM_PRESET : ${PRENOM}"
else
    read -rp "$(echo -e "${BOLD}Prénom de l'utilisateur :${NC} ")" PRENOM
fi

if [[ -z "$PRENOM" ]]; then
    error "Le prénom ne peut pas être vide."
    exit 1
fi

# Normalisation : minuscules, sans accents basiques
PRENOM_LOWER=$(echo "$PRENOM" | tr '[:upper:]' '[:lower:]' | iconv -f utf-8 -t ascii//TRANSLIT 2>/dev/null || echo "$PRENOM" | tr '[:upper:]' '[:lower:]')

SUBDOMAIN="${PRENOM_LOWER}.lenouvel.me"
DEPLOY_PATH_HINT="~/${SUBDOMAIN}"
DB_NAME="${SSH_USER}_${PRENOM_LOWER}"
DB_USER="${SSH_USER}_${PRENOM_LOWER}"

title "── Récapitulatif ──────────────────────"
echo -e "  Prénom          : ${BOLD}${PRENOM}${NC}"
echo -e "  Sous-domaine    : ${BOLD}https://${SUBDOMAIN}${NC}"
echo -e "  Chemin serveur  : ${BOLD}\$HOME/${SUBDOMAIN}${NC}"
echo -e "  Base de données : ${BOLD}${DB_NAME}${NC}"
echo -e "  Utilisateur DB  : ${BOLD}${DB_USER}${NC}"
echo ""

if [[ -n "${CONFIRM_PRESET:-}" ]]; then CONFIRM="$CONFIRM_PRESET"; else read -rp "$(echo -e "${BOLD}Continuer ? [o/N] :${NC} ")" CONFIRM; fi
if [[ "$CONFIRM" != "o" && "$CONFIRM" != "O" ]]; then
    info "Annulé."
    exit 0
fi

# ── Secrets à générer localement ─────────────────────────────────────────────
if [[ "$UPDATE_MODE" == false ]]; then
    title "── Génération des secrets ──────────────"

    APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
    success "APP_SECRET généré"

    APP_ENCRYPTION_KEY=$(php -r "echo base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());")
    success "APP_ENCRYPTION_KEY générée"

    JWT_PASSPHRASE=$(php -r "echo bin2hex(random_bytes(24));")
    success "JWT_PASSPHRASE générée"

    title "── Base de données ─────────────────────"
    warn "o2switch : les bases de données doivent être créées via cPanel (Bases de données MySQL)."
    echo ""
    echo -e "  Nom de la base  : ${BOLD}${DB_NAME}${NC}"
    echo -e "  Utilisateur DB  : ${BOLD}${DB_USER}${NC}"
    echo ""

    if [[ -n "${DB_PASSWORD_PRESET:-}" ]]; then
        DB_PASSWORD="$DB_PASSWORD_PRESET"
        success "Mot de passe DB chargé depuis DB_PASSWORD_PRESET"
    else
        read -rsp "$(echo -e "${BOLD}Mot de passe MySQL pour ${DB_USER} (sera stocké dans .env.local) :${NC} ")" DB_PASSWORD
        echo ""
    fi

    if [[ -z "$DB_PASSWORD" ]]; then
        error "Le mot de passe DB ne peut pas être vide."
        exit 1
    fi

    DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}?serverVersion=mariadb-10.6.0&charset=utf8mb4"
fi

# ── Vérification SSH ──────────────────────────────────────────────────────────
title "── Connexion SSH ───────────────────────"
info "Test de connexion SSH vers ${SSH_USER}@${SSH_HOST}…"

if ! ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" -o ConnectTimeout=10 "${SSH_USER}@${SSH_HOST}" "echo OK" &>/dev/null; then
    error "Impossible de se connecter en SSH."
    error "Vérifiez que votre IP est bien whitelistée dans cPanel → Outils → 'Autorisation SSH'"
    exit 1
fi
success "Connexion SSH OK"

# Récupère le $HOME réel du serveur
REMOTE_HOME=$(ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" 'echo $HOME')
DEPLOY_PATH="${REMOTE_HOME}/${SUBDOMAIN}"
info "Chemin de déploiement : ${DEPLOY_PATH}"

# ── Déploiement ──────────────────────────────────────────────────────────────
title "── Déploiement en cours ────────────────────"

if [[ "$UPDATE_MODE" == true ]]; then
    info "Mode mise à jour : git pull + composer + cache + migrations + assets"
    ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "cd ${DEPLOY_PATH} && \
         git pull origin main && \
         ${COMPOSER_BIN} install --no-interaction --prefer-dist --no-progress --no-dev && \
         ${PHP_BIN} bin/console cache:clear --env=prod && \
         ${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --env=prod && \
         ${PHP_BIN} bin/console asset-map:compile" && \
    success "✅ Déploiement réussi !" || \
    { error "❌ Erreur lors du déploiement."; exit 1; }
else
    info "Mode primo déploiement : clonage repo + setup"
    ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" "mkdir -p ${DEPLOY_PATH} && cd ${DEPLOY_PATH} && git clone ${GIT_REPO} . && composer install --no-interaction --prefer-dist --no-progress" && \
    success "✅ Déploiement réussi !" || \
    { error "❌ Erreur lors du déploiement."; exit 1; }
fi

echo ""
success "Application disponible à : https://${SUBDOMAIN}"