#!/usr/bin/env bash
# =============================================================================
# HomeCloud — Script de déploiement sur o2switch
# Usage :
#   bash bin/deploy.sh           → Premier déploiement (setup complet)
#   bash bin/deploy.sh --update  → Mise à jour du code uniquement
# =============================================================================

# ── Chargement des secrets locaux (non versionnés) ────────────────────────────
# Crée un fichier .secrets à la racine du projet (voir .gitignore).
# Il sera sourcé automatiquement si présent.
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

# ── Prérequis o2switch ────────────────────────────────────────────────────────
# Avant de lancer ce script, vous devez avoir :
#   1. Whitelisté votre IP dans cPanel → Outils → "Autorisation SSH"
#      Votre IP : https://mon-ip.io
#   2. (Optionnel mais recommandé) Ajouté votre clé SSH publique sur le serveur :
#      ssh-keygen -t ed25519 -f ~/.ssh/o2switch
#      Puis copier ~/.ssh/o2switch.pub dans ~/.ssh/authorized_keys sur le serveur
#      (via cPanel File Manager ou ssh-copy-id)
# ─────────────────────────────────────────────────────────────────────────────

# ── Couleurs ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# ── Vérification pré-déploiement ──────────────────────────────────────────────
echo -e "${YELLOW}⚠️  Avant de lancer ce script, vérifie :${NC}"
echo -e "  1. La Pull Request est bien mergée sur main sur GitHub."
echo -e "  2. Tu es bien sur la branche main (git checkout main)."
echo -e "  3. Le push sur origin/main est fait (git push origin main)."
echo -e "${BLUE}Astuce : Tu peux vérifier la branche avec 'git branch --show-current'${NC}"
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
# PHP CLI sur o2switch (ajuster si version différente)
PHP_BIN="/usr/local/bin/php"
COMPOSER_BIN="/opt/cpanel/composer/bin/composer"

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
# Le chemin réel sera calculé côté serveur ($HOME peut différer de /home/user sur o2switch)
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

    # ── Demande du mot de passe DB ────────────────────────────────────────────────
    title "── Base de données ─────────────────────"
    warn "o2switch : les bases de données doivent être créées via cPanel (Bases de données MySQL)."
    echo ""
    echo -e "  Nom de la base  : ${BOLD}${DB_NAME}${NC}"
    echo -e "  Utilisateur DB  : ${BOLD}${DB_USER}${NC}"
    echo ""

    if [[ -n "$DB_PASSWORD_PRESET" ]]; then
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

# Récupère le $HOME réel du serveur pour construire le chemin de déploiement
REMOTE_HOME=$(ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" 'echo $HOME')
DEPLOY_PATH="${REMOTE_HOME}/${SUBDOMAIN}"
info "Chemin de déploiement : ${DEPLOY_PATH}"

# ── Déploiement via SSH ───────────────────────────────────────────────────────
title "── Déploiement ─────────────────────────"

ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" bash -s -- \
    "$SUBDOMAIN" "$GIT_REPO" "$GIT_BRANCH" "$PHP_BIN" "$COMPOSER_BIN" "${JWT_PASSPHRASE:-}" \
    <<'SSHSCRIPT'
set -euo pipefail

SUBDOMAIN="${1:-}"
GIT_REPO="${2:-}"
GIT_BRANCH="${3:-}"
PHP_BIN="${4:-}"
COMPOSER_BIN="${5:-}"
JWT_PASSPHRASE="${6:-}"

# Calcul du chemin réel côté serveur (évite les erreurs de chemin absolu)
DEPLOY_PATH="${HOME}/${SUBDOMAIN}"

echo "→ Répertoire : ${DEPLOY_PATH}"

# Clone ou mise à jour du repo
if [ -d "${DEPLOY_PATH}/.git" ]; then
    echo "→ Mise à jour du repo…"
    git -C "${DEPLOY_PATH}" fetch origin
    git -C "${DEPLOY_PATH}" reset --hard "origin/${GIT_BRANCH}"
    # Log du commit déployé
    DEPLOYED_COMMIT=$(git -C "${DEPLOY_PATH}" rev-parse HEAD)
    echo "→ Version déployée : $DEPLOYED_COMMIT"
else
    echo "→ Clone du repo…"
    git clone --branch "${GIT_BRANCH}" --depth 1 "${GIT_REPO}" "${DEPLOY_PATH}"
    # Log du commit déployé
    DEPLOYED_COMMIT=$(git -C "${DEPLOY_PATH}" rev-parse HEAD)
    echo "→ Version déployée : $DEPLOYED_COMMIT"
fi

# Composer install
echo "→ Installation des dépendances Composer…"
${PHP_BIN} ${COMPOSER_BIN} install \
    --working-dir="${DEPLOY_PATH}" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# Répertoires var/
mkdir -p "${DEPLOY_PATH}/var/cache/prod"
mkdir -p "${DEPLOY_PATH}/var/log"
mkdir -p "${DEPLOY_PATH}/var/storage"
mkdir -p "${DEPLOY_PATH}/var/tailwind"
chmod -R 775 "${DEPLOY_PATH}/var"

echo "→ Déploiement côté serveur terminé."
SSHSCRIPT

# ── Compilation Tailwind CSS en local + upload ────────────────────────────────
# o2switch : /tmp est noexec, le binaire Bun/Tailwind ne peut pas s'exécuter.
# Solution : compiler localement et uploader le CSS compilé via SCP.
title "── Compilation Tailwind CSS (local) ───"
info "Compilation Tailwind en local…"
php bin/console tailwind:build --minify
success "Tailwind compilé"

info "Upload de app.built.css vers le serveur…"
scp ${SSH_KEY_OPTS} -P "${SSH_PORT}" \
    var/tailwind/app.built.css \
    "${SSH_USER}@${SSH_HOST}:${DEPLOY_PATH}/var/tailwind/app.built.css"
success "CSS uploadé"

success "Repo déployé sur le serveur"

# ── Envoi du .env.local ───────────────────────────────────────────────────────
if [[ "$UPDATE_MODE" == false ]]; then
    # Construction du .env.local (uniquement en déploiement initial)
    ENV_LOCAL=$(cat <<ENVEOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
APP_URL=https://${SUBDOMAIN}
CORS_ALLOW_ORIGIN=^https://${PRENOM_LOWER}\\.lenouvel\\.me$
APP_ENCRYPTION_KEY=${APP_ENCRYPTION_KEY}
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_PASSPHRASE}
JWT_TTL=3600
ENVEOF
)
    info "Envoi du .env.local…"
    echo "$ENV_LOCAL" | ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "cat > ${DEPLOY_PATH}/.env.local && chmod 600 ${DEPLOY_PATH}/.env.local"
    success ".env.local déployé"

    # ── Génération des clés JWT ───────────────────────────────────────────────────
    info "Génération des clés JWT…"
    ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "cd ${DEPLOY_PATH} && ${PHP_BIN} bin/console lexik:jwt:generate-keypair --overwrite --no-interaction --env=prod"
    success "Clés JWT générées"
fi

# ── Tentative de création de la DB via SSH ────────────────────────────────────
if [[ "$UPDATE_MODE" == false ]]; then
    title "── Base de données MySQL ────────────────"

    DB_CREATED=false
    if ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "mysql -u ${DB_USER} -p${DB_PASSWORD} -e 'SELECT 1;' ${DB_NAME}" &>/dev/null 2>&1; then
        success "Base de données ${DB_NAME} accessible"
        DB_CREATED=true
    fi

    if [ "$DB_CREATED" = false ]; then
        warn "La base de données n'est pas accessible via SSH."
        echo ""
        echo -e "${BOLD}════════════════════════════════════════════════════════${NC}"
        echo -e "${BOLD}  ⚠️  ACTION MANUELLE REQUISE dans cPanel o2switch${NC}"
        echo -e "${BOLD}════════════════════════════════════════════════════════${NC}"
        echo ""
        echo "  1. Connectez-vous sur : https://cpanel.o2switch.net"
        echo "     (ou votre URL cPanel o2switch)"
        echo ""
        echo "  2. Rubrique 'Bases de données MySQL' :"
        echo "     → Créer la base :      ${BOLD}${DB_NAME}${NC}"
        echo "     → Créer l'utilisateur : ${BOLD}${DB_USER}${NC}"
        echo "       Mot de passe :         ${BOLD}(celui que vous avez saisi)${NC}"
        echo "     → Associer l'utilisateur à la base"
        echo "       avec ${BOLD}TOUS LES PRIVILÈGES${NC}"
        echo ""
        echo "  3. Rubrique 'Sous-domaines' :"
        echo "     → Créer le sous-domaine : ${BOLD}${PRENOM_LOWER}.lenouvel.me${NC}"
        echo "       Répertoire racine :      ${BOLD}${DEPLOY_PATH}/public${NC}"
        echo ""
        echo "  4. Une fois la DB créée, relancez ce script ou exécutez"
        echo "     manuellement sur le serveur :"
        echo ""
        echo "     ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
        echo "     cd ${DEPLOY_PATH}"
        echo "     ${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction"
        echo ""
        echo -e "${BOLD}════════════════════════════════════════════════════════${NC}"
        echo ""

        if [[ -n "${RUN_MIGRATIONS_PRESET:-}" ]]; then RUN_MIGRATIONS="$RUN_MIGRATIONS_PRESET"; else read -rp "$(echo -e "${BOLD}La DB est-elle configurée dans cPanel ? Lancer les migrations maintenant ? [o/N] :${NC} ")" RUN_MIGRATIONS; fi
    else
        RUN_MIGRATIONS="o"
    fi
else
    RUN_MIGRATIONS="o"
fi

# ── Migrations ────────────────────────────────────────────────────────────────
if [[ "$RUN_MIGRATIONS" == "o" || "$RUN_MIGRATIONS" == "O" ]]; then
    info "Lancement des migrations Doctrine…"
    ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "cd ${DEPLOY_PATH} && ${PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod"
    success "Migrations appliquées"

    # Cache Symfony
    info "Warm-up du cache Symfony…"
    ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
        "cd ${DEPLOY_PATH} && ${PHP_BIN} bin/console cache:warmup --env=prod" 2>/dev/null || true
    success "Cache généré"
fi

# ── Résumé final ──────────────────────────────────────────────────────────────
title "═══════════════════════════════════════"
if [[ "$UPDATE_MODE" == true ]]; then
    title "  Mise à jour terminée 🎉"
else
    title "  Déploiement terminé 🎉"
fi
title "═══════════════════════════════════════"
echo ""
echo -e "  URL de l'API    : ${GREEN}https://${SUBDOMAIN}/api${NC}"
echo -e "  Swagger UI      : ${GREEN}https://${SUBDOMAIN}/api/docs${NC}"
echo -e "  Chemin serveur  : ${BOLD}${DEPLOY_PATH}${NC}"
echo ""

# ── Création du premier utilisateur (premier déploiement uniquement) ──────────
if [[ "$UPDATE_MODE" == false ]]; then
    echo -e "${YELLOW}  ► Créer le premier utilisateur :${NC}"
    echo ""
    echo "    ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
    echo "    cd ${DEPLOY_PATH}"
    echo "    ${PHP_BIN} bin/console app:create-user <email> <password> \"${PRENOM}\""
    echo ""

    if [[ -z "${CREATE_USER_PRESET:-}" ]]; then
        read -rp "$(echo -e "${BOLD}Créer le premier utilisateur maintenant ? [o/N] :${NC} ")" CREATE_USER
    else
        CREATE_USER="$CREATE_USER_PRESET"
    fi

    if [[ "$CREATE_USER" == "o" || "$CREATE_USER" == "O" ]]; then
        read -rp "Email : " USER_EMAIL
        read -rsp "Mot de passe : " USER_PASSWORD
        echo ""
        ssh ${SSH_KEY_OPTS} -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
            "cd ${DEPLOY_PATH} && ${PHP_BIN} bin/console app:create-user '${USER_EMAIL}' '${USER_PASSWORD}' '${PRENOM}' --env=prod"
        success "Utilisateur créé"
    fi
fi
