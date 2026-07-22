#!/usr/bin/env bash
# Installe un binaire ffmpeg/ffprobe statique dans var/bin/.
# o2switch est mutualisé : pas de root, pas d'apt. Idempotent et non bloquant —
# une absence de ffmpeg dégrade la vignette vidéo, elle ne casse pas le déploiement.
set -uo pipefail   # PAS -e : on veut sortir 0 même en cas d'échec

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN_DIR="${PROJECT_DIR}/var/bin"

mkdir -p "${BIN_DIR}"

# Vérification idempotence : si ffmpeg est déjà en place et fonctionnel, terminer
if [[ -x "${BIN_DIR}/ffmpeg" ]] && "${BIN_DIR}/ffmpeg" -version >/dev/null 2>&1; then
    echo "✅ ffmpeg déjà installé"
    exit 0
fi

echo "⏳ Téléchargement de ffmpeg (John Van Sickle build statique)..."

DOWNLOAD_URL="https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz"
TEMP_DIR=$(mktemp -d)
trap "rm -rf '${TEMP_DIR}'" EXIT

cd "${TEMP_DIR}"

# Téléchargement
if ! curl -fsSL -o ffmpeg.tar.xz "${DOWNLOAD_URL}"; then
    echo "⚠ Impossible de télécharger ffmpeg — vignettes vidéo indisponibles"
    exit 0
fi

# Extraction — o2switch n'a ni xz ni unxz (tar -xf échoue silencieusement sur
# le flux compressé). python3 + son module lzma standard sont présents et
# servent de décompresseur de repli.
if command -v xz >/dev/null 2>&1 || command -v unxz >/dev/null 2>&1; then
    EXTRACT_OK=$(tar -xf ffmpeg.tar.xz && echo yes || echo no)
elif command -v python3 >/dev/null 2>&1; then
    EXTRACT_OK=$(python3 -c 'import lzma,sys; sys.stdout.buffer.write(lzma.open("ffmpeg.tar.xz","rb").read())' | tar -x && echo yes || echo no)
else
    EXTRACT_OK=no
fi

if [[ "${EXTRACT_OK}" != "yes" ]]; then
    echo "⚠ Impossible d'extraire ffmpeg — vignettes vidéo indisponibles"
    exit 0
fi

# Localiser les binaires
FFMPEG=$(find . -name ffmpeg -type f | head -1)
FFPROBE=$(find . -name ffprobe -type f | head -1)

if [[ -z "$FFMPEG" ]] || [[ -z "$FFPROBE" ]]; then
    echo "⚠ ffmpeg/ffprobe introuvables dans l'archive — vignettes vidéo indisponibles"
    exit 0
fi

# Installation
cp "${FFMPEG}" "${BIN_DIR}/ffmpeg"
cp "${FFPROBE}" "${BIN_DIR}/ffprobe"
chmod +x "${BIN_DIR}/ffmpeg" "${BIN_DIR}/ffprobe"

if "${BIN_DIR}/ffmpeg" -version >/dev/null 2>&1; then
    echo "✅ ffmpeg installé avec succès"
    exit 0
else
    echo "⚠ ffmpeg installé mais non fonctionnel — vignettes vidéo indisponibles"
    exit 0
fi
