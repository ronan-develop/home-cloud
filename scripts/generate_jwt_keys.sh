#!/usr/bin/env bash
set -euo pipefail

# Génère une paire de clés RSA pour LexikJWTAuthenticationBundle
# Usage: bash scripts/generate_jwt_keys.sh

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JWT_DIR="$BASE_DIR/config/jwt"

echo "Création du dossier $JWT_DIR"
mkdir -p "$JWT_DIR"

echo "Génération de la clé privée RSA 4096 (fichier: $JWT_DIR/private.pem)"
openssl genpkey -algorithm RSA -out "$JWT_DIR/private.pem" -pkeyopt rsa_keygen_bits:4096

echo "Extraction de la clé publique (fichier: $JWT_DIR/public.pem)"
openssl rsa -pubout -in "$JWT_DIR/private.pem" -out "$JWT_DIR/public.pem"

echo "Réglage des permissions: private.pem => 600, public.pem => 644"
chmod 600 "$JWT_DIR/private.pem"
chmod 644 "$JWT_DIR/public.pem"

echo
echo "Clés générées dans: $JWT_DIR"
ls -l "$JWT_DIR"

cat <<'WARN'

ATTENTION:
- NE COMMITTEZ PAS `config/jwt/private.pem` dans le dépôt.
- Ajoutez `config/jwt/private.pem` à votre `.gitignore` local si nécessaire.
- Protégez l'accès aux clés sur vos environnements de production (variables d'environnement, vault, accès restreint).

Après génération: configurez LexikJWTAuthenticationBundle (voir config/packages/lexik_jwt_authentication.yaml)
WARN
