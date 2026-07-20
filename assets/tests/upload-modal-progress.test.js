import { jest, describe, test, expect, beforeEach } from '@jest/globals';

await import('../components/hc-folder-list.js');
const { openUploadModal, closeUploadModal } = await import('../js/upload-modal.js');

/**
 * TDD RED — #239 : la barre de progression d'upload ne se met pas à jour.
 *
 * Simule un vrai upload XHR (comme le fait réellement le navigateur) plutôt
 * que d'appeler directement les callbacks internes, pour reproduire le
 * chemin exact emprunté en production.
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
    open() {}
    setRequestHeader() {}
    send() {}
    fireUploadProgress(loaded, total) {
        this.upload._listeners['progress']?.({ lengthComputable: true, loaded, total });
    }
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

describe('openUploadModal — barre de progression (#239)', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        createdXHRs = [];
        window.HC = { getToken: async () => 'test-token', userId: 'user-1' };
        global.XMLHttpRequest = FakeXHR;
        global.fetch = jest.fn().mockResolvedValue({ ok: false });
    });

    afterEach(() => {
        closeUploadModal();
    });

    test("la largeur de la barre suit la progression de l'upload XHR", async () => {
        const file = new File(['x'.repeat(1000)], 'photo.jpg', { type: 'image/jpeg' });

        await openUploadModal([file], {
            folderId: 'folder-1',
            folders: [{ id: 'folder-1', name: 'Root' }],
        });

        const submitBtn = document.getElementById('hc-upload-submit-btn');
        submitBtn.click();

        await flushMicrotasks();
        expect(createdXHRs.length).toBe(1);

        createdXHRs[0].fireUploadProgress(500, 1000);
        await flushMicrotasks();

        const bar = document.querySelector('.upload-item__bar');
        expect(bar.style.width).toBe('50%');

        createdXHRs[0].fireUploadProgress(1000, 1000);
        await flushMicrotasks();
        expect(bar.style.width).toBe('100%');

        createdXHRs[0].fireLoad(201, { id: 'f1' });
        await flushMicrotasks();
    });

    test('chaque fichier a sa propre barre de progression, indépendamment des autres (upload multiple)', async () => {
        const fileA = new File(['a'.repeat(1000)], 'a.jpg', { type: 'image/jpeg' });
        const fileB = new File(['b'.repeat(1000)], 'b.jpg', { type: 'image/jpeg' });

        await openUploadModal([fileA, fileB], {
            folderId: 'folder-1',
            folders: [{ id: 'folder-1', name: 'Root' }],
        });

        document.getElementById('hc-upload-submit-btn').click();
        await flushMicrotasks();
        expect(createdXHRs.length).toBe(2);

        createdXHRs[0].fireUploadProgress(300, 1000);
        await flushMicrotasks();

        const bars = document.querySelectorAll('.upload-item__bar');
        expect(bars[0].style.width).toBe('30%');
        expect(bars[1].style.width).toBe('0%');

        createdXHRs[1].fireUploadProgress(700, 1000);
        await flushMicrotasks();
        expect(bars[0].style.width).toBe('30%');
        expect(bars[1].style.width).toBe('70%');
    });

    test('fichiers de même nom : chaque barre reste indépendante (pas de collision de clé)', async () => {
        const fileA = new File(['a'.repeat(1000)], 'IMG_0001.jpg', { type: 'image/jpeg' });
        const fileB = new File(['b'.repeat(1000)], 'IMG_0001.jpg', { type: 'image/jpeg' });

        await openUploadModal([fileA, fileB], {
            folderId: 'folder-1',
            folders: [{ id: 'folder-1', name: 'Root' }],
        });

        document.getElementById('hc-upload-submit-btn').click();
        await flushMicrotasks();
        expect(createdXHRs.length).toBe(2);

        createdXHRs[0].fireUploadProgress(200, 1000);
        await flushMicrotasks();

        const bars = document.querySelectorAll('.upload-item__bar');
        expect(bars[0].style.width).toBe('20%');
        expect(bars[1].style.width).toBe('0%');
    });
});
