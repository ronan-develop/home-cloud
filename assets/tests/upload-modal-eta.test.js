import { jest, describe, test, expect, beforeEach } from '@jest/globals';

await import('../components/hc-folder-list.js');
const { openUploadModal, closeUploadModal } = await import('../js/upload-modal.js');

/**
 * TDD RED — #336 : volume transféré + vitesse + temps restant estimé
 * pendant l'upload.
 *
 * Réutilise le pattern FakeXHR de upload-modal-progress.test.js (#239)
 * pour reproduire le chemin réel emprunté en production, avec une horloge
 * injectée pour rendre le calcul de vitesse déterministe.
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

describe('openUploadModal — volume, vitesse, ETA (#336)', () => {
    let now;

    beforeEach(() => {
        document.body.innerHTML = '';
        createdXHRs = [];
        window.HC = { getToken: async () => 'test-token', userId: 'user-1' };
        global.XMLHttpRequest = FakeXHR;
        global.fetch = jest.fn().mockResolvedValue({ ok: false });

        now = 1_000_000;
        jest.spyOn(Date, 'now').mockImplementation(() => now);
    });

    afterEach(() => {
        closeUploadModal();
        jest.restoreAllMocks();
    });

    test('affiche le volume transféré (X Mo / Y Go), la vitesse et le temps restant estimé', async () => {
        // 2 Go au total, upload à vitesse constante pour un calcul d'ETA simple
        const totalSize = 2 * 1024 * 1024 * 1024;
        const file = new File([''], 'video.mp4', { type: 'video/mp4' });
        Object.defineProperty(file, 'size', { value: totalSize });

        await openUploadModal([file], {
            folderId: 'folder-1',
            folders: [{ id: 'folder-1', name: 'Root' }],
        });

        document.getElementById('hc-upload-submit-btn').click();
        await flushMicrotasks();
        expect(createdXHRs.length).toBe(1);

        // 1er échantillon : 100 Mo transférés à t0
        const loaded1 = 100 * 1024 * 1024;
        createdXHRs[0].fireUploadProgress(loaded1, totalSize);
        await flushMicrotasks();

        // 2e échantillon 1s plus tard : 200 Mo transférés → 100 Mo/s
        now += 1000;
        const loaded2 = 200 * 1024 * 1024;
        createdXHRs[0].fireUploadProgress(loaded2, totalSize);
        await flushMicrotasks();

        const meta = document.querySelector('.upload-item__meta');
        expect(meta).not.toBeNull();
        expect(meta.textContent).toContain('Mo');
        expect(meta.textContent).toContain('Go');
        expect(meta.textContent).toMatch(/Mo\/s/);
        // (2048 Mo - 200 Mo) / 100 Mo/s ≈ 18s restantes
        expect(meta.textContent).toMatch(/restant/);

        createdXHRs[0].fireLoad(201, { id: 'f1' });
        await flushMicrotasks();
    });

    test('la barre de progression ne fige plus de couleur en inline (couleur pilotée par CSS .upload-item__bar → var(--hc-accent))', async () => {
        const file = new File(['x'.repeat(1000)], 'photo.jpg', { type: 'image/jpeg' });

        await openUploadModal([file], {
            folderId: 'folder-1',
            folders: [{ id: 'folder-1', name: 'Root' }],
        });

        const bar = document.querySelector('.upload-item__bar');
        expect(bar.classList.contains('upload-item__bar')).toBe(true);
        expect(bar.getAttribute('style')).not.toMatch(/background/);
    });
});
