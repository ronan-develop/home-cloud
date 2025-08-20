#!/bin/bash
# Script pour lancer les tests PHPUnit en local (base MariaDB locale, pas de Docker)
# Usage : ./test-local.sh [commande]

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$PROJECT_ROOT/lenouvel.me"

cd "$APP_DIR" || exit 1

CMD_IN_APP="$@"
if [ -z "$CMD_IN_APP" ]; then
  # Génération automatique du schéma de la base de test (MariaDB locale)
  CMD_IN_APP="bin/console doctrine:schema:update --env=test --force && vendor/bin/phpunit"
fi

$CMD_IN_APP
