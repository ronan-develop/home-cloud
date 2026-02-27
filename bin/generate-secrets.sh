#!/usr/bin/env bash
# =============================================================================
# generate-secrets.sh — Génération des secrets avant mise en production
#
# Usage : bash bin/generate-secrets.sh
#
# Ce script génère APP_ENCRYPTION_KEY et APP_SECRET dans .env.local (gitignored).
# À exécuter une seule fois par environnement (dev local, staging, prod).
# ATTENTION : régénérer APP_ENCRYPTION_KEY invalide tous les fichiers chiffrés.
# =============================================================================

set -euo pipefail

ENV_LOCAL=".env.local"

# ---------------------------------------------------------------------------
# Vérifications préalables
# ---------------------------------------------------------------------------
if ! command -v php &>/dev/null; then
    echo "❌  PHP n'est pas disponible dans le PATH." >&2
    exit 1
fi

if php -r "exit(extension_loaded('sodium') ? 0 : 1);" 2>/dev/null; then
    SODIUM_OK=true
else
    SODIUM_OK=false
fi

# ---------------------------------------------------------------------------
# Génération des clés
# ---------------------------------------------------------------------------
if $SODIUM_OK; then
    # Clé sodium 32 bytes encodée en base64 (XChaCha20-Poly1305)
    ENCRYPTION_KEY=$(php -r "echo base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());")
else
    # Fallback : openssl si sodium absent
    ENCRYPTION_KEY=$(openssl rand -base64 32)
    echo "⚠️  Extension sodium absente — clé générée via openssl (moins sûre)." >&2
fi

# APP_SECRET : 32 octets en hexadécimal
APP_SECRET=$(php -r "echo bin2hex(random_bytes(32));")

# ---------------------------------------------------------------------------
# Écriture dans .env.local
# ---------------------------------------------------------------------------
if [[ -f "$ENV_LOCAL" ]]; then
    # Remplacer les lignes existantes si déjà présentes
    if grep -q "^APP_ENCRYPTION_KEY=" "$ENV_LOCAL"; then
        sed -i "s|^APP_ENCRYPTION_KEY=.*|APP_ENCRYPTION_KEY=${ENCRYPTION_KEY}|" "$ENV_LOCAL"
    else
        echo "APP_ENCRYPTION_KEY=${ENCRYPTION_KEY}" >> "$ENV_LOCAL"
    fi

    if grep -q "^APP_SECRET=" "$ENV_LOCAL"; then
        sed -i "s|^APP_SECRET=.*|APP_SECRET=${APP_SECRET}|" "$ENV_LOCAL"
    else
        echo "APP_SECRET=${APP_SECRET}" >> "$ENV_LOCAL"
    fi
else
    cat > "$ENV_LOCAL" <<EOF
# Généré par bin/generate-secrets.sh — NE PAS COMMITER
APP_ENCRYPTION_KEY=${ENCRYPTION_KEY}
APP_SECRET=${APP_SECRET}
EOF
fi

# ---------------------------------------------------------------------------
# Résumé
# ---------------------------------------------------------------------------
echo ""
echo "✅  Secrets générés et écrits dans ${ENV_LOCAL}"
echo ""
echo "  APP_ENCRYPTION_KEY : ${ENCRYPTION_KEY}"
echo "  APP_SECRET         : ${APP_SECRET}"
echo ""
echo "⚠️  ATTENTION : .env.local est gitignored."
echo "     En production, injectez ces valeurs via les variables d'environnement"
echo "     du serveur (ex: FPM pool, .htaccess SetEnv, ou panel o2switch)."
echo ""
echo "⚠️  Ne régénérez PAS APP_ENCRYPTION_KEY si des fichiers sont déjà chiffrés"
echo "     — ils deviendraient illisibles."
echo ""
