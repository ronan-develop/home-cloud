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
 * @param {File} file
 * @param {(blob: Blob, meta: { filename: string, chunkIndex: number, totalChunks: number }) => Promise<void>} uploadChunk
 * @param {{ chunkSizeBytes?: number }} [options]
 */
export async function uploadFileInChunks(file, uploadChunk, { chunkSizeBytes = CHUNK_SIZE_BYTES } = {}) {
    const totalChunks = Math.max(1, Math.ceil(file.size / chunkSizeBytes));

    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
        const start = chunkIndex * chunkSizeBytes;
        const end = Math.min(start + chunkSizeBytes, file.size);
        const blob = file.slice(start, end);

        await uploadChunk(blob, { filename: file.name, chunkIndex, totalChunks });
    }
}
