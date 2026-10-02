#!/usr/bin/env bash
# =============================================================================
# HomeCloud — Rotation des logs applicatifs d'une instance (#606)
#
# Usage : bash bin/rotate-logs.sh [dossier_de_logs]     (défaut : var/log)
#
# Constat du 2026-10-02 : var/log/messenger.log atteignait ~50 Mo sur chacune
# des 7 instances, sans aucune purge. Le worker Messenger (cron toutes les
# minutes, `>> messenger.log`) réimprime sa bannière à chaque démarrage : ce
# fichier ne contient quasiment que ce bruit, mais y reçoit aussi les erreurs.
#
# Pour chaque *.log de plus de ROTATE_LOGS_MAX_BYTES (défaut 10 Mo) :
#   - archive compressée dans <fichier>.1.gz (les anciennes sont décalées, au
#     plus ROTATE_LOGS_KEEP archives conservées, défaut 3) ;
#   - le fichier est TRONQUÉ, jamais renommé : un worker qui tourne garde son
#     descripteur ouvert (`>>` = O_APPEND) et continue d'écrire au bon endroit,
#     sans qu'il faille le redémarrer. Quelques lignes écrites entre la copie
#     et la troncature peuvent être perdues — acceptable pour des logs.
#
# Observation pure : si l'archivage échoue, le log n'est PAS tronqué, et le
# script sort toujours en 0 — il ne doit jamais faire échouer son appelant
# (le déploiement nocturne).
# =============================================================================

set -uo pipefail

LOG_DIR="${1:-var/log}"
MAX_BYTES="${ROTATE_LOGS_MAX_BYTES:-10485760}"
KEEP="${ROTATE_LOGS_KEEP:-3}"

# Les archives contiennent des logs : même confidentialité que les logs.
umask 077

[[ -d "$LOG_DIR" ]] || exit 0

rotate_one() {
    local file="$1" size i
    size=$(stat -c %s "$file" 2>/dev/null) || return 0
    [[ "$size" -gt "$MAX_BYTES" ]] || return 0

    # Archiver d'abord dans un fichier temporaire : tant que la copie n'est pas
    # complète, rien n'est décalé ni tronqué.
    if ! gzip -c "$file" > "${file}.new.gz" 2>/dev/null; then
        rm -f "${file}.new.gz"
        echo "⚠ rotation : archivage de ${file} impossible, log conservé tel quel" >&2
        return 0
    fi

    rm -f "${file}.${KEEP}.gz"
    for ((i = KEEP - 1; i >= 1; i--)); do
        [[ -f "${file}.${i}.gz" ]] && mv "${file}.${i}.gz" "${file}.$((i + 1)).gz"
    done
    mv "${file}.new.gz" "${file}.1.gz"
    : > "$file"
    echo "↻ rotation : ${file} ($((size / 1024)) Ko) archivé dans ${file}.1.gz"
}

for log in "$LOG_DIR"/*.log; do
    [[ -f "$log" ]] && rotate_one "$log"
done

exit 0
