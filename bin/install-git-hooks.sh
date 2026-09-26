#!/bin/bash
# Installe les git hooks versionnés (bin/git-hooks/) dans .git/hooks/.
# .git/hooks/ n'est jamais suivi par git — sans ce script, un hook écrit à
# la main sur une machine n'existe que sur cette machine. Lancé
# automatiquement après composer install/update (voir composer.json).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
HOOKS_SRC="$REPO_ROOT/bin/git-hooks"
HOOKS_DEST="$REPO_ROOT/.git/hooks"

if [[ ! -d "$REPO_ROOT/.git" ]]; then
    echo "⚠️  Pas un dépôt git (ou .git absent) — installation des hooks ignorée."
    exit 0
fi

for hook in "$HOOKS_SRC"/*; do
    name=$(basename "$hook")
    cp "$hook" "$HOOKS_DEST/$name"
    chmod +x "$HOOKS_DEST/$name"
    echo "✅ Hook installé : $name"
done
