import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';

const { initExplorerDrop } = await import('../js/explorer-drop.js');

function fakeFileEntry(file) {
    return {
        isFile: true,
        isDirectory: false,
        name: file.name,
        file(successCb) { successCb(file); },
    };
}

function fakeDirEntry(name, children) {
    let served = false;
    return {
        isFile: false,
        isDirectory: true,
        name,
        createReader() {
            return {
                readEntries(successCb) {
                    if (served) { successCb([]); } else { served = true; successCb(children); }
                },
            };
        },
    };
}

function fakeItem(entry) {
    return { webkitGetAsEntry: () => entry };
}

async function flushMicrotasks(times = 20) {
    for (let i = 0; i < times; i++) await Promise.resolve();
}

describe('initExplorerDrop', () => {
    let handle;

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="main-import-card">
                <input type="hidden" name="folder_id" value="folder-42">
                <input type="file" id="import-file-input" multiple>
            </div>
        `;
        handle = initExplorerDrop();
    });

    afterEach(() => {
        handle.destroy();
    });

    test("sélection via le bouton Parcourir (#import-file-input) → hc:files-selected, pas de soumission de formulaire native (#336)", async () => {
        const fileInput = document.getElementById('import-file-input');
        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });
        Object.defineProperty(fileInput, 'files', { value: [file], configurable: true });

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        await flushMicrotasks();

        expect(received).toHaveBeenCalledTimes(1);
        const { files, folderId } = received.mock.calls[0][0].detail;
        expect(folderId).toBe('folder-42');
        expect(files).toHaveLength(1);
        expect(files[0]).toBe(file);
    });

    test('sélection vide (annulation du sélecteur natif) : aucun événement émis', async () => {
        const fileInput = document.getElementById('import-file-input');
        Object.defineProperty(fileInput, 'files', { value: [], configurable: true });

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        await flushMicrotasks();

        expect(received).not.toHaveBeenCalled();
    });

    test("dépose d'un dossier local → hc:files-selected avec relativePath annoté sur chaque File", async () => {
        const inDirFile = new File(['x'], 'scan.pdf');
        const dir = fakeDirEntry('2026-07-10-BMA', [fakeFileEntry(inDirFile)]);

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
        dropEvent.dataTransfer = { items: [fakeItem(dir)] };
        document.dispatchEvent(dropEvent);

        await flushMicrotasks();

        expect(received).toHaveBeenCalledTimes(1);
        const { files, folderId } = received.mock.calls[0][0].detail;
        expect(folderId).toBe('folder-42');
        expect(files).toHaveLength(1);
        expect(files[0]._hcRelativePath).toBe('2026-07-10-BMA');
    });

    test('fichier isolé (pas de dossier) : pas de relativePath annoté', async () => {
        const alone = new File(['y'], 'alone.pdf');

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
        dropEvent.dataTransfer = { items: [fakeItem(fakeFileEntry(alone))] };
        document.dispatchEvent(dropEvent);

        await flushMicrotasks();

        const { files } = received.mock.calls[0][0].detail;
        expect(files[0]._hcRelativePath).toBeUndefined();
    });

    test('fichiers vides (taille 0) sont filtrés', async () => {
        const empty = new File([], 'empty.pdf');

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
        dropEvent.dataTransfer = { items: [fakeItem(fakeFileEntry(empty))] };
        document.dispatchEvent(dropEvent);

        await flushMicrotasks();

        expect(received).not.toHaveBeenCalled();
    });
});
