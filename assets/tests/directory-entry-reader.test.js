import { jest, describe, test, expect } from '@jest/globals';

const { readDroppedEntries } = await import('../js/directory-entry-reader.js');

/**
 * Fakes pour DataTransferItem / FileSystemEntry (API navigateur non disponible
 * sous jsdom) — reproduisent le contrat callback-based réel :
 *   FileSystemFileEntry.file(successCb, errorCb)
 *   FileSystemDirectoryEntry.createReader().readEntries(successCb, errorCb)
 *   (readEntries doit être appelé en boucle jusqu'à un tableau vide, par spec)
 */
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
                    if (served) {
                        successCb([]);
                    } else {
                        served = true;
                        successCb(children);
                    }
                },
            };
        },
    };
}

function fakeItem(entry) {
    return { webkitGetAsEntry: () => entry };
}

describe('readDroppedEntries', () => {
    test('un fichier déposé seul → relativePath vide', async () => {
        const file = new File(['x'], 'photo.jpg');
        const items = [fakeItem(fakeFileEntry(file))];

        const results = await readDroppedEntries(items);

        expect(results).toEqual([{ file, relativePath: '' }]);
    });

    test('un dossier contenant un fichier direct → relativePath = nom du dossier', async () => {
        const file = new File(['x'], 'scan1.pdf');
        const dir = fakeDirEntry('2026-07-10-BMA', [fakeFileEntry(file)]);
        const items = [fakeItem(dir)];

        const results = await readDroppedEntries(items);

        expect(results).toEqual([{ file, relativePath: '2026-07-10-BMA' }]);
    });

    test('sous-dossiers imbriqués sur plusieurs niveaux', async () => {
        const fileA = new File(['x'], 'a.pdf');
        const fileB = new File(['y'], 'b.pdf');
        const nested = fakeDirEntry('Nested', [fakeFileEntry(fileB)]);
        const root = fakeDirEntry('Root', [fakeFileEntry(fileA), nested]);
        const items = [fakeItem(root)];

        const results = await readDroppedEntries(items);

        expect(results).toContainEqual({ file: fileA, relativePath: 'Root' });
        expect(results).toContainEqual({ file: fileB, relativePath: 'Root/Nested' });
    });

    test('plusieurs éléments déposés en même temps (dossier + fichier isolé)', async () => {
        const inDir = new File(['x'], 'in.pdf');
        const alone = new File(['y'], 'alone.pdf');
        const dir = fakeDirEntry('Folder', [fakeFileEntry(inDir)]);
        const items = [fakeItem(dir), fakeItem(fakeFileEntry(alone))];

        const results = await readDroppedEntries(items);

        expect(results).toContainEqual({ file: inDir, relativePath: 'Folder' });
        expect(results).toContainEqual({ file: alone, relativePath: '' });
    });

    test('ignore les items sans webkitGetAsEntry ou entrée nulle', async () => {
        const items = [{ webkitGetAsEntry: () => null }, {}];

        const results = await readDroppedEntries(items);

        expect(results).toEqual([]);
    });
});
