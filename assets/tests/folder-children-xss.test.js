import { jest, describe, test, expect, beforeEach } from '@jest/globals';

// F9 de l'audit sécurité : loadFolderChildren() injectait item.name (nom de
// fichier/dossier fourni par l'utilisateur) dans innerHTML sans échappement.
// Un nom uploadé contenant une balise <img onerror=...> s'exécutait dans le DOM.
await import('../js/folder-children.js');

describe('folder-children — XSS via nom de fichier', () => {
    beforeEach(() => {
        document.body.innerHTML = '<div data-testid="file-list"></div>';
        global.fetch = jest.fn();
    });

    test('un nom de fichier contenant une balise <img> ne doit pas créer d\'élément <img> dans le DOM', async () => {
        const payload = '<img src=x onerror=alert(1)>.txt';
        global.fetch.mockResolvedValue({
            ok: true,
            json: async () => ({
                items: [{ id: '1', isFolder: false, name: payload, mimeType: 'text/plain', size: 10, createdAt: '2026-01-01' }],
            }),
        });

        await window.loadFolderChildren('folder-1');

        const fileList = document.querySelector('[data-testid="file-list"]');
        expect(fileList.querySelector('img[onerror]')).toBeNull();
        expect(fileList.querySelectorAll('.file-name img').length).toBe(0);
    });

    test('le nom doit apparaître comme texte affiché, pas comme balise interprétée', async () => {
        const payload = '<img src=x onerror=alert(1)>.txt';
        global.fetch.mockResolvedValue({
            ok: true,
            json: async () => ({
                items: [{ id: '2', isFolder: false, name: payload, mimeType: 'text/plain', size: 10, createdAt: '2026-01-01' }],
            }),
        });

        await window.loadFolderChildren('folder-1');

        const nameEl = document.querySelector('.file-name');
        expect(nameEl.textContent).toBe(payload);
    });
});
