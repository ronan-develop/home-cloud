/**
 * HomeCloud — Découpage d'un fichier en tranches pour l'upload Takeout (#466).
 *
 * Un ZIP Google Photos Takeout peut dépasser 512M — plafond dur de
 * l'hébergement mutualisé o2switch, confirmé non contournable via .user.ini
 * en conditions réelles (archives ~2GB). Ce module découpe un File en
 * tranches envoyées séquentiellement, indépendant du DOM/Stimulus pour
 * rester testable en isolation (I/O réseau injectée via `uploadChunk`).
 */

// Bien sous le plafond serveur observé (512M) pour laisser de la marge aux
// en-têtes multipart et à une éventuelle limite plus basse sur une autre
// instance o2switch.
export const CHUNK_SIZE_BYTES = 50 * 1024 * 1024; // 50 Mo

/**
 * Découpe `file` en tranches de `chunkSizeBytes` et les envoie
 * séquentiellement via `uploadChunk(blob, meta)`, en attendant chaque
 * réponse avant d'envoyer la suivante (jamais en parallèle : le serveur
 * réassemble par écriture en append, l'ordre d'arrivée doit être garanti).
 *
 * `onChunkUploaded` est optionnel : permet à l'appelant de suivre la
 * progression chunk par chunk plutôt qu'attendre la fin du fichier entier
 * (un ZIP de plusieurs GB découpé en ~40 tranches de 50 Mo restait sinon
 * silencieux plusieurs minutes avant que la barre ne bouge, constaté en
 * conditions réelles).
 *
 * `resumeFromChunkIndex` est optionnel (défaut 0) : reprend l'envoi à partir
 * de ce chunk plutôt que depuis le début, pour un fichier déjà partiellement
 * uploadé (#481 — l'utilisateur a fermé l'onglet en cours d'envoi et
 * re-sélectionne le même ZIP). L'appelant est responsable d'avoir vérifié au
 * préalable, via hashChunk(), que le premier chunk local correspond bien à
 * ce qui a déjà été écrit côté serveur (cf. TakeoutImportFilesStatusController).
 *
 * @param {File} file
 * @param {(blob: Blob, meta: { filename: string, chunkIndex: number, totalChunks: number }) => Promise<void>} uploadChunk
 * @param {{ chunkSizeBytes?: number, onChunkUploaded?: (meta: { chunkIndex: number, totalChunks: number }) => void, resumeFromChunkIndex?: number }} [options]
 */
export async function uploadFileInChunks(file, uploadChunk, { chunkSizeBytes = CHUNK_SIZE_BYTES, onChunkUploaded, resumeFromChunkIndex = 0 } = {}) {
    const totalChunks = Math.max(1, Math.ceil(file.size / chunkSizeBytes));

    for (let chunkIndex = resumeFromChunkIndex; chunkIndex < totalChunks; chunkIndex += 1) {
        const start = chunkIndex * chunkSizeBytes;
        const end = Math.min(start + chunkSizeBytes, file.size);
        const blob = file.slice(start, end);

        await uploadChunk(blob, { filename: file.name, chunkIndex, totalChunks });
        onChunkUploaded?.({ chunkIndex, totalChunks });
    }
}

/**
 * Hash SHA-256 (hexadécimal) du contenu d'un blob, via SubtleCrypto natif
 * (aucune dépendance ajoutée). Utilisé pour vérifier, avant de reprendre un
 * upload interrompu, que le premier chunk local correspond bien au premier
 * chunk déjà écrit côté serveur (#481) — nom+taille de fichier seuls ne
 * suffisent pas à exclure une coïncidence (fichier différent, même nom/taille).
 *
 * @param {Blob} blob
 * @returns {Promise<string>}
 */
export async function hashChunk(blob) {
    const buffer = await blob.arrayBuffer();
    const digest = await crypto.subtle.digest('SHA-256', buffer);

    return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}
