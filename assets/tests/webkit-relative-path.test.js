import { describe, test, expect } from '@jest/globals';

const { annotateWebkitRelativePaths } = await import('../js/directory-entry-reader.js');

/**
 * #238 — sélection via <input webkitdirectory> (menu "Importer un dossier").
 * file.webkitRelativePath est peuplé nativement par le navigateur
 * (ex. "2026-07-10-BMA/scans/scan1.pdf") ; on en extrait le dossier parent
 * pour l'annoter en _hcRelativePath, même contrat que le drag & drop.
 */
function withWebkitRelativePath(file, relativePath) {
    Object.defineProperty(file, 'webkitRelativePath', { value: relativePath });
    return file;
}

describe('annotateWebkitRelativePaths', () => {
    test('extrait le dossier parent de webkitRelativePath', () => {
        const file = withWebkitRelativePath(new File(['x'], 'scan1.pdf'), '2026-07-10-BMA/scan1.pdf');

        annotateWebkitRelativePaths([file]);

        expect(file._hcRelativePath).toBe('2026-07-10-BMA');
    });

    test('sous-dossiers imbriqués', () => {
        const file = withWebkitRelativePath(new File(['x'], 'scan2.pdf'), '2026-07-10-BMA/scans/scan2.pdf');

        annotateWebkitRelativePaths([file]);

        expect(file._hcRelativePath).toBe('2026-07-10-BMA/scans');
    });

    test('sans webkitRelativePath : pas d\'annotation', () => {
        const file = new File(['x'], 'plain.pdf');

        annotateWebkitRelativePaths([file]);

        expect(file._hcRelativePath).toBeUndefined();
    });
});
