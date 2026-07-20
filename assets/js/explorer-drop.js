import { readDroppedEntries } from './directory-entry-reader.js';

/**
 * Active le drag & drop de fichiers/dossiers sur la zone d'import
 * (`#main-import-card`), en réutilisant la structure du dossier local déposé
 * (#238) : chaque fichier annoté de son relativePath, consommé par
 * upload-modal.js pour recréer l'arborescence côté serveur.
 *
 * Auparavant dupliqué à l'identique dans explorer.html.twig et home.html.twig.
 */
export function initExplorerDrop() {
    const importCard = document.getElementById('main-import-card');
    if (!importCard) return { destroy() {} };

    let dragCounter = 0;

    const onDragEnter = (e) => {
        e.preventDefault();
        dragCounter++;
        importCard.classList.add('drop-active');
    };
    const onDragOver = (e) => { e.preventDefault(); };
    const onDragLeave = (e) => {
        dragCounter--;
        // relatedTarget null = le curseur a quitté la fenêtre du navigateur
        if (dragCounter <= 0 || e.relatedTarget === null) {
            dragCounter = 0;
            importCard.classList.remove('drop-active');
        }
    };
    const onDrop = async (e) => {
        e.preventDefault();
        dragCounter = 0;
        importCard.classList.remove('drop-active');

        const entries = await readDroppedEntries(e.dataTransfer.items);
        const files = entries
            .filter(({ file }) => file.size > 0)
            .map(({ file, relativePath }) => {
                if (relativePath) {
                    file._hcRelativePath = relativePath;
                }
                return file;
            });

        if (files.length === 0) return;

        const folderIdInput = importCard.querySelector('input[name="folder_id"]');
        const folderId = folderIdInput ? folderIdInput.value : '';
        document.dispatchEvent(new CustomEvent('hc:files-selected', { detail: { files, folderId } }));
    };

    document.addEventListener('dragenter', onDragEnter);
    document.addEventListener('dragover', onDragOver);
    document.addEventListener('dragleave', onDragLeave);
    document.addEventListener('drop', onDrop);

    return {
        destroy() {
            document.removeEventListener('dragenter', onDragEnter);
            document.removeEventListener('dragover', onDragOver);
            document.removeEventListener('dragleave', onDragLeave);
            document.removeEventListener('drop', onDrop);
        },
    };
}
