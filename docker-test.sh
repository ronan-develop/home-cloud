#!/bin/bash
# Script d'automatisation pour lancer les tests dans le conteneur Docker PHP 8.3 + SQLite
# Usage : ./docker-test.sh [commande]
# Par défaut : lance les tests PHPUnit

IMAGE=php83-sqlite-dev
CONTAINER_NAME=php83-sqlite-test

# Détection du dossier projet (racine) et du dossier app Symfony
PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$PROJECT_ROOT/lenouvel.me"
DATA_DIR="$PROJECT_ROOT/data"

# Build l'image si elle n'existe pas
docker image inspect $IMAGE > /dev/null 2>&1 || \
  docker build -f "$PROJECT_ROOT/Docker/Dockerfile.php83-sqlite" -t $IMAGE "$PROJECT_ROOT"

# Commande à exécuter (par défaut : vendor/bin/phpunit)
CMD_IN_CONTAINER="$@"
if [ -z "$CMD_IN_CONTAINER" ]; then
  # Génération automatique du schéma de la base de test (compatible SQLite)
  CMD_IN_CONTAINER="bin/console doctrine:schema:update --env=test --force && vendor/bin/phpunit"
fi

docker run --rm -it \
  -v "$APP_DIR":/app \
  -v "$DATA_DIR":/app/data \
  -w /app \
  --name $CONTAINER_NAME \
  $IMAGE bash -c "$CMD_IN_CONTAINER"
