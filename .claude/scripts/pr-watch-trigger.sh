#!/bin/bash
# Hook PostToolUse(Bash) : détecte "gh pr create", lance la surveillance des
# checks CI en arrière-plan sans jamais merger. Le merge reste un geste
# humain explicite (cf. CLAUDE.md).
input=$(cat)
cmd=$(echo "$input" | jq -r '.tool_input.command // empty')
if [[ "$cmd" != *"gh pr create"* ]]; then
    exit 0
fi
pr_url=$(echo "$input" | jq -r '.tool_response.stdout // .tool_response.output // empty')
pr_number=$(echo "$pr_url" | grep -oE '/pull/[0-9]+' | grep -oE '[0-9]+')
if [[ -z "$pr_number" ]]; then
    exit 0
fi
nohup bash "$(dirname "$0")/pr-watch.sh" "$pr_number" >/dev/null 2>&1 &
echo "{\"systemMessage\": \"Surveillance CI démarrée en fond pour la PR #${pr_number}.\"}"
