#!/bin/bash
# Surveille les checks CI d'une PR fraîchement créée (déclenché par le hook
# PostToolUse sur "gh pr create"), sans jamais merger. Écrit un fichier de
# statut lu par le hook Notification/prochain PostToolUse.
set -uo pipefail

PR_NUMBER="$1"
STATUS_FILE="/tmp/claude-pr-watch-${PR_NUMBER}.status"

echo "pending" > "$STATUS_FILE"

for i in $(seq 1 40); do
    result=$(gh pr checks "$PR_NUMBER" 2>&1)
    if ! echo "$result" | grep -q "pending"; then
        if echo "$result" | grep -qi "fail"; then
            echo "failed" > "$STATUS_FILE"
            echo "$result" >> "$STATUS_FILE"
        else
            echo "ready" > "$STATUS_FILE"
        fi
        exit 0
    fi
    sleep 15
done

echo "timeout" > "$STATUS_FILE"
