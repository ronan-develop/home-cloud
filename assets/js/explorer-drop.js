import { readDroppedEntries } from './directory-entry-reader.js';

/**
 * Active l'import de fichiers/dossiers sur la zone `#main-import-card`,
 * par drag & drop ou via le sélecteur natif (bouton "Parcourir") : les deux
 * chemins convergent vers le même événement `hc:files-selected`, consommé
 * par upload-modal.js (progress bar, #336) — jamais de soumission de
 * formulaire native, qui ne permettrait aucune progression.
 *
 * La structure du dossier local déposé (#238) est préservée : chaque fichier
 * est annoté de son relativePath.
 *
 * Auparavant dupliqué à l'identique dans explorer.html.twig et home.html.twig.
 */
export function initExplorerDrop() {
    const importCard = document.getElementById('main-import-card');
    if (!importCard) return { destroy() {} };

    const fileInput = importCard.querySelector('#import-file-input');

    let dragCounter = 0;

    const dispatchFilesSelected = (files) => {
        if (files.length === 0) return;
        const folderIdInput = importCard.querySelector('input[name="folder_id"]');
        const folderId = folderIdInput ? folderIdInput.value : '';
        document.dispatchEvent(new CustomEvent('hc:files-selected', { detail: { files, folderId } }));
    };

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

        dispatchFilesSelected(files);
    };
    const onFileInputChange = () => {
        dispatchFilesSelected(Array.from(fileInput.files));
        fileInput.value = '';
    };

    document.addEventListener('dragenter', onDragEnter);
    document.addEventListener('dragover', onDragOver);
    document.addEventListener('dragleave', onDragLeave);
    document.addEventListener('drop', onDrop);
    fileInput?.addEventListener('change', onFileInputChange);

    return {
        destroy() {
            document.removeEventListener('dragenter', onDragEnter);
            document.removeEventListener('dragover', onDragOver);
            document.removeEventListener('dragleave', onDragLeave);
            document.removeEventListener('drop', onDrop);
            fileInput?.removeEventListener('change', onFileInputChange);
        },
    };
}
