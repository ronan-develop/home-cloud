import { jest, describe, test, expect, beforeEach } from '@jest/globals';

const { createUploadFn } = await import('../js/upload-modal.js');

/**
 * #238 — le fichier annoté d'un relativePath (dossier local glissé-déposé,
 * via explorer-drop.js/directory-entry-reader.js) doit voir ce champ propagé
 * dans le multipart envoyé au serveur, pour que FileUploadController recrée
 * l'arborescence (DefaultFolderService::ensureSubfolderPath).
 */
let capturedFormData = null;

class FakeXHR {
    constructor() {
        this.upload = { addEventListener() {} };
        this._listeners = {};
    }
    addEventListener(evt, cb) { this._listeners[evt] = cb; }
    open() {}
    setRequestHeader() {}
    send(formData) {
        capturedFormData = formData;
        this.status = 201;
        this.responseText = JSON.stringify({ id: 'f1' });
        this._listeners['load']?.();
    }
}

describe('createUploadFn — propagation du relativePath', () => {
    beforeEach(() => {
        capturedFormData = null;
        global.XMLHttpRequest = FakeXHR;
    });

    test('un fichier annoté _hcRelativePath envoie le champ relativePath', async () => {
        const uploadFn = createUploadFn('token', 'folder-1', null, null);
        const file = new File(['x'], 'scan.pdf');
        file._hcRelativePath = '2026-07-10-BMA';

        await uploadFn(file, {}, () => {});

        expect(capturedFormData.get('relativePath')).toBe('2026-07-10-BMA');
    });

    test('un fichier sans relativePath n\'envoie pas le champ', async () => {
        const uploadFn = createUploadFn('token', 'folder-1', null, null);
        const file = new File(['x'], 'alone.pdf');

        await uploadFn(file, {}, () => {});

        expect(capturedFormData.get('relativePath')).toBeNull();
    });
});
