import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';

await import('../components/hc-folder-list.js');
const { openUploadModal, closeUploadModal } = await import('../js/upload-modal.js');

/**
 * TDD RED — #339 : mode "album" de la modale d'upload.
 *
 * Contrairement au mode standard (destination = dossier au choix), l'import
 * direct dans un album n'a pas de notion de dossier : on masque le
 * sélecteur, on force processSync=1 sur chaque upload (pour récupérer le
 * mediaId immédiatement, cf. FileUploadController), puis on associe chaque
 * média uploadé à l'album via POST /api/v1/albums/{albumId}/medias.
 *
 * Réutilise le pattern FakeXHR de upload-modal-progress.test.js (#239).
 */
let createdXHRs = [];

class FakeXHR {
    constructor() {
        createdXHRs.push(this);
        this._listeners = {};
        this.upload = {
            _listeners: {},
            addEventListener(evt, cb) { this._listeners[evt] = cb; },
        };
    }
    addEventListener(evt, cb) { this._listeners[evt] = cb; }
    open(method, url) { this._method = method; this._url = url; }
    setRequestHeader() {}
    send(formData) { this._formData = formData; }
    fireLoad(status, body) {
        this.status = status;
        this.responseText = JSON.stringify(body);
        this._listeners['load']?.();
    }
}

async function flushMicrotasks(times = 20) {
    for (let i = 0; i < times; i++) {
        await Promise.resolve();
    }
}

describe('openUploadModal — mode album (#339)', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        createdXHRs = [];
        window.HC = { getToken: async () => 'test-token', userId: 'user-1' };
        global.XMLHttpRequest = FakeXHR;
        global.fetch = jest.fn().mockResolvedValue({ ok: false });
    });

    afterEach(() => {
        closeUploadModal();
        jest.restoreAllMocks();
    });

    test('mode album : pas de sélecteur de destination affiché', async () => {
        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });

        await openUploadModal([file], { albumId: 'album-1' });

        const folderList = document.querySelector('#hc-upload-folder-list');
        expect(folderList).toBeNull();
    });

    test('mode album : chaque upload envoie processSync=1 sans folderId', async () => {
        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });

        await openUploadModal([file], { albumId: 'album-1' });

        const submitBtn = document.getElementById('hc-upload-submit-btn');
        submitBtn.click();
        await flushMicrotasks();

        expect(createdXHRs.length).toBe(1);
        expect(createdXHRs[0]._url).toBe('/api/v1/files');

        const formData = createdXHRs[0]._formData;
        expect(formData.get('processSync')).toBe('1');
        expect(formData.get('folderId')).toBeNull();
    });

    test('mode album : après upload réussi, associe le mediaId à l\'album via POST /api/v1/albums/{id}/medias', async () => {
        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });

        global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ id: 'album-1', mediaCount: 1 }) });

        await openUploadModal([file], { albumId: 'album-1' });

        const submitBtn = document.getElementById('hc-upload-submit-btn');
        submitBtn.click();
        await flushMicrotasks();

        createdXHRs[0].fireLoad(201, { id: 'file-1', mediaId: 'media-42' });
        await flushMicrotasks(40);

        expect(global.fetch).toHaveBeenCalledWith(
            '/api/v1/albums/album-1/medias',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ mediaId: 'media-42' }),
            }),
        );
    });

    test('mode album : pas d\'association si le fichier uploadé n\'a pas produit de média (mediaId absent)', async () => {
        const file = new File(['x'], 'doc.pdf', { type: 'application/pdf' });

        global.fetch = jest.fn();

        await openUploadModal([file], { albumId: 'album-1' });

        const submitBtn = document.getElementById('hc-upload-submit-btn');
        submitBtn.click();
        await flushMicrotasks();

        createdXHRs[0].fireLoad(201, { id: 'file-1', mediaId: null });
        await flushMicrotasks(40);

        expect(global.fetch).not.toHaveBeenCalledWith(
            expect.stringContaining('/medias'),
            expect.anything(),
        );
    });
});
