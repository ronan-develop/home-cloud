/**
 * Lit récursivement les entrées d'un drop (DataTransferItemList), fichiers ET
 * dossiers, en résolvant le chemin relatif à la racine du drop — pour
 * recréer l'arborescence côté serveur (#238).
 *
 * S'appuie sur DataTransferItem.webkitGetAsEntry() : seule API permettant de
 * distinguer un dossier d'un fichier lors d'un drag & drop natif (DataTransfer.files
 * "aplatit" un dossier sans exposer sa structure).
 *
 * @param {DataTransferItemList|Array} items
 * @returns {Promise<{file: File, relativePath: string}[]>}
 */
export async function readDroppedEntries(items) {
    const entries = Array.from(items)
        .map(item => (typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null))
        .filter(Boolean);

    const results = [];
    for (const entry of entries) {
        await readEntry(entry, '', results);
    }
    return results;
}

function readEntry(entry, basePath, results) {
    if (entry.isFile) {
        return new Promise((resolve, reject) => {
            entry.file(file => {
                results.push({ file, relativePath: basePath });
                resolve();
            }, reject);
        });
    }

    if (entry.isDirectory) {
        const childPath = basePath ? `${basePath}/${entry.name}` : entry.name;
        return readDirectory(entry, childPath, results);
    }

    return Promise.resolve();
}

/**
 * Annote chaque File de son dossier parent déduit de `webkitRelativePath`
 * (peuplé nativement par le navigateur pour <input webkitdirectory>, ex.
 * "2026-07-10-BMA/scans/scan1.pdf") — même contrat que readDroppedEntries,
 * pour le menu "Importer un dossier" (#238).
 *
 * @param {File[]} files
 * @returns {File[]} les mêmes fichiers, mutés en place
 */
export function annotateWebkitRelativePaths(files) {
    files.forEach(file => {
        const rel = file.webkitRelativePath;
        if (!rel) return;
        const idx = rel.lastIndexOf('/');
        if (idx > 0) {
            file._hcRelativePath = rel.slice(0, idx);
        }
    });
    return files;
}

async function readDirectory(dirEntry, path, results) {
    const reader = dirEntry.createReader();
    let entries;
    do {
        entries = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
        for (const child of entries) {
            await readEntry(child, path, results);
        }
    } while (entries.length > 0);
}
