import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import TakeoutImportController from '../controllers/takeout_import_controller.js';

/**
 * TDD RED — #458 : deux manques UX constatés en conditions réelles sur
 * /import/takeout après un upload réel (46s, aucun retour visuel pendant
 * l'attente) :
 *   1. aucune confirmation des fichiers ZIP sélectionnés avant l'envoi
 *   2. la barre de progression ne bouge jamais pendant l'attente
 *      (onComplete n'était notifié qu'au tout dernier tick du poller)
 */
describe('takeout-import controller (#458)', () => {
    let application;

    function html() {
        document.body.innerHTML = `
            <div data-controller="takeout-import">
                <div data-takeout-import-target="form">
                    <input type="file" hidden data-takeout-import-target="input"
                           data-action="change->takeout-import#onFilesSelected">
                    <ul data-takeout-import-target="fileList"></ul>
                    <button type="button" disabled data-takeout-import-target="submit"
                            data-action="click->takeout-import#submit">Démarrer</button>
                    <p hidden data-takeout-import-target="error"></p>
                </div>
                <div hidden data-takeout-import-target="progress">
                    <div data-takeout-import-target="bar"></div>
                    <p data-takeout-import-target="status"></p>
                    <p data-takeout-import-target="counts"></p>
                </div>
            </div>
        `;
    }

    function makeFile(name) {
        return new File(['contenu'], name, { type: 'application/zip' });
    }

    function setInputFiles(files) {
        const input = document.querySelector('[data-takeout-import-target="input"]');
        Object.defineProperty(input, 'files', { value: files, configurable: true });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    beforeEach(() => {
        html();
        application = Application.start();
        application.register('takeout-import', TakeoutImportController);
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('affiche le nom de chaque fichier ZIP sélectionné', () => {
        setInputFiles([makeFile('takeout-001.zip'), makeFile('takeout-002.zip')]);

        const list = document.querySelector('[data-takeout-import-target="fileList"]');
        const names = Array.from(list.querySelectorAll('li')).map((li) => li.textContent.trim());

        expect(names).toEqual(['takeout-001.zip', 'takeout-002.zip']);
    });

    test('vide la liste si l\'utilisateur désélectionne les fichiers', () => {
        setInputFiles([makeFile('takeout-001.zip')]);
        setInputFiles([]);

        const list = document.querySelector('[data-takeout-import-target="fileList"]');
        expect(list.querySelectorAll('li')).toHaveLength(0);
    });

    test('la barre de progression se met à jour pendant l\'attente (extracting), pas seulement à la fin', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            // POST /api/v1/takeout-imports (création, sans fichier)
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            // POST /api/v1/takeout-imports/import-1/files (1 ZIP)
            .mockResolvedValueOnce({ ok: true })
            // POST /api/v1/takeout-imports/import-1/start
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            // GET immédiat après le start (rendu initial pending)
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) })
            // premier tick du poller : extracting
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'extracting', processedCount: 0, totalMediaCount: null }) });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        // Premier tick du poller (backoff initial 5s).
        await jest.advanceTimersByTimeAsync(5000);

        const status = document.querySelector('[data-takeout-import-target="status"]');
        expect(status.textContent).toBe('Extraction des archives…');

        jest.useRealTimers();
    });

    // #466 : un seul gros POST multi-fichiers dépassait la limite de taille
    // de requête du serveur mutualisé (413) dès que l'utilisateur
    // sélectionnait plusieurs ZIP Takeout volumineux d'un coup. Chaque ZIP
    // doit désormais partir dans sa propre requête, sans rien changer côté
    // sélection utilisateur (toujours groupée en un clic).
    test('envoie chaque ZIP dans sa propre requête après création de l\'import', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip'), makeFile('takeout-002.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        const calls = global.fetch.mock.calls.map((c) => c[0]);
        expect(calls).toEqual([
            '/api/v1/takeout-imports',
            '/api/v1/takeout-imports/import-1/files',
            '/api/v1/takeout-imports/import-1/files',
            '/api/v1/takeout-imports/import-1/start',
            '/api/v1/takeout-imports/import-1',
        ]);

        jest.useRealTimers();
    });

    test('affiche une erreur si l\'envoi d\'un ZIP échoue, sans appeler start', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: false, status: 413 });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        const calls = global.fetch.mock.calls.map((c) => c[0]);
        expect(calls).toEqual(['/api/v1/takeout-imports', '/api/v1/takeout-imports/import-1/files']);

        const error = document.querySelector('[data-takeout-import-target="error"]');
        expect(error.hidden).toBe(false);

        jest.useRealTimers();
    });
});
