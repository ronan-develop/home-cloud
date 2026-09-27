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
                    <div hidden data-takeout-import-target="fileProgressWrapper">
                        <div data-takeout-import-target="fileBar"></div>
                        <p data-takeout-import-target="fileStatus"></p>
                    </div>
                    <p data-takeout-import-target="patienceMessage"></p>
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
            // GET /api/v1/takeout-imports/pending (aucune reprise en cours)
            .mockResolvedValueOnce({ ok: false, status: 404 })
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
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
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
            '/api/v1/takeout-imports/pending',
            '/api/v1/takeout-imports',
            '/api/v1/takeout-imports/import-1/files',
            '/api/v1/takeout-imports/import-1/files',
            '/api/v1/takeout-imports/import-1/start',
            '/api/v1/takeout-imports/import-1',
        ]);

        jest.useRealTimers();
    });

    // #477 : constaté en conditions réelles avec 11 ZIP Takeout — après le
    // clic, le bouton devient grisé mais rien n'indique un envoi en cours
    // pendant potentiellement plusieurs minutes (upload chunké #466/#471).
    // La zone de progression doit s'afficher dès le début de l'upload, pas
    // seulement après le "start" (qui déclenche le traitement serveur).
    test('affiche la progression de l\'upload dès le premier fichier envoyé, avant le start', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip'), makeFile('takeout-002.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true }) // upload fichier 1
            .mockResolvedValueOnce({ ok: true }) // upload fichier 2
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        const progress = document.querySelector('[data-takeout-import-target="progress"]');
        const status = document.querySelector('[data-takeout-import-target="status"]');

        expect(progress.hidden).toBe(true);

        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        // La recherche d'un import à reprendre (GET pending, 404 ici) se
        // résout d'abord, avant que l'affichage de la progression ne soit
        // à son tour écrasé par la suite de l'upload — attendre l'état
        // "progression visible" plutôt qu'un nombre fixe de microtâches,
        // fragile face au nombre exact d'await internes à _resolveResumePlan.
        for (let i = 0; i < 20 && progress.hidden; i += 1) {
            await Promise.resolve();
        }
        expect(progress.hidden).toBe(false);
        expect(status.textContent).toBe('Envoi de 1 / 2 fichiers…');

        await jest.advanceTimersByTimeAsync(0);

        jest.useRealTimers();
    });

    // #481 : upload potentiellement long (plusieurs minutes avec un gros
    // ZIP en chunks) — un message qui tourne rassure sur le fait que rien
    // n'est bloqué et rappelle de garder l'onglet ouvert.
    test('affiche un message de patience dès le début de l\'upload, qui change au fil du temps', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        const patienceMessage = document.querySelector('[data-takeout-import-target="patienceMessage"]');

        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        // La recherche d'un import à reprendre (GET pending, 404 ici) se
        // résout d'abord, avant l'affichage du message de patience —
        // attendre l'état "message affiché" plutôt qu'un nombre fixe de
        // microtâches, fragile face au nombre exact d'await internes.
        for (let i = 0; i < 20 && patienceMessage.textContent === ''; i += 1) {
            await Promise.resolve();
        }

        const firstMessage = patienceMessage.textContent;
        expect(firstMessage).not.toBe('');

        await jest.advanceTimersByTimeAsync(4000);
        expect(patienceMessage.textContent).not.toBe(firstMessage);

        await jest.advanceTimersByTimeAsync(0);
        jest.useRealTimers();
    });

    test('arrête le message de patience une fois l\'upload terminé (passage au polling)', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        const patienceMessage = document.querySelector('[data-takeout-import-target="patienceMessage"]');

        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        expect(patienceMessage.textContent).toBe('');

        jest.useRealTimers();
    });

    test('affiche une erreur si l\'envoi d\'un ZIP échoue, sans appeler start', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockResolvedValueOnce({ ok: false, status: 413 });

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        const calls = global.fetch.mock.calls.map((c) => c[0]);
        expect(calls).toEqual(['/api/v1/takeout-imports/pending', '/api/v1/takeout-imports', '/api/v1/takeout-imports/import-1/files']);

        const error = document.querySelector('[data-takeout-import-target="error"]');
        expect(error.hidden).toBe(false);

        jest.useRealTimers();
    });

    // #481 : une mise en veille prolongée du PC pendant l'upload coupe la
    // connexion — le navigateur rejette alors le fetch en cours avec un
    // TypeError générique ("Failed to fetch"), trop technique et anxiogène
    // tel quel. La progression déjà envoyée reste acquise côté serveur ;
    // le message doit rassurer et inviter à relancer, pas donner
    // l'impression d'une perte de données (constaté en conditions réelles).
    test('affiche un message rassurant (pas le TypeError brut) si la connexion est coupée en cours d\'envoi', async () => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
        setInputFiles([makeFile('takeout-001.zip')]);

        global.fetch = jest.fn()
            .mockResolvedValueOnce({ ok: false, status: 404 }) // GET pending
            .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-1', status: 'pending' }) })
            .mockRejectedValueOnce(new TypeError('Failed to fetch'));

        const button = document.querySelector('[data-takeout-import-target="submit"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await jest.advanceTimersByTimeAsync(0);

        const error = document.querySelector('[data-takeout-import-target="error"]');
        expect(error.hidden).toBe(false);
        expect(error.textContent).not.toContain('Failed to fetch');
        expect(error.textContent.toLowerCase()).toContain('relancez');

        jest.useRealTimers();
    });

    // #481 : reprise d'un upload interrompu par fermeture d'onglet — un
    // import "pending" existant doit être réutilisé (pas de nouvel import
    // créé), et un fichier déjà partiellement uploadé (nom + hash du premier
    // chunk correspondants) doit reprendre à partir du bon chunkIndex.
    describe('reprise d\'un import interrompu', () => {
        function makeFile(name, content = 'contenu') {
            return new File([content], name, { type: 'application/zip' });
        }

        test('réutilise l\'import pending existant au lieu d\'en créer un nouveau', async () => {
            jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
            setInputFiles([makeFile('takeout-001.zip')]);

            global.fetch = jest.fn()
                // GET pending : un import existe déjà
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) })
                // GET files/status : aucun fichier encore uploadé pour cet import
                .mockResolvedValueOnce({ ok: true, json: async () => [] })
                .mockResolvedValueOnce({ ok: true }) // upload complet du fichier
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) }) // start
                .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

            const button = document.querySelector('[data-takeout-import-target="submit"]');
            button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            await jest.advanceTimersByTimeAsync(0);

            const calls = global.fetch.mock.calls.map((c) => c[0]);
            expect(calls).not.toContain('/api/v1/takeout-imports'); // pas de POST de création
            expect(calls).toContain('/api/v1/takeout-imports/import-existing/files');
            expect(calls).toContain('/api/v1/takeout-imports/import-existing/start');

            jest.useRealTimers();
        });

        // Pas de fake timers ici : crypto.subtle.digest() (utilisé par
        // hashChunk pour vérifier l'intégrité avant reprise) délègue à un
        // thread pool natif Node, hors de la portée des fake timers de
        // Jest — advanceTimersByTimeAsync/setTimeout(0) ne suffisent pas à
        // attendre cette étape ; un vrai délai (50ms, largement suffisant
        // pour un digest SHA-256 sur quelques octets de test) est requis.
        test('reprend un fichier déjà partiellement uploadé quand le hash du premier chunk correspond', async () => {
            const file = makeFile('takeout-001.zip', 'AAA');
            setInputFiles([file]);

            // Référence : php -r "echo hash('sha256', 'AAA');"
            const hashOfAAA = 'cb1ad2119d8fafb69566510ee712661f9f14b83385006ef92aec47f523a38358';

            global.fetch = jest.fn()
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) })
                .mockResolvedValueOnce({
                    ok: true,
                    json: async () => [{ filename: 'takeout-001.zip', chunkIndex: 0, hashOfFirstChunk: hashOfAAA }],
                })
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) }) // start
                .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

            const button = document.querySelector('[data-takeout-import-target="submit"]');
            button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 50));

            // Le fichier tient en un seul chunk (petit contenu de test) déjà
            // confirmé chunkIndex=0 : plus aucun chunk à envoyer, donc aucun
            // POST .../files — directement le start.
            const calls = global.fetch.mock.calls.map((c) => c[0]);
            expect(calls).not.toContain('/api/v1/takeout-imports/import-existing/files');
            expect(calls).toContain('/api/v1/takeout-imports/import-existing/start');
        });

        test('renvoie depuis 0 si le hash du premier chunk ne correspond pas (fichier différent)', async () => {
            const file = makeFile('takeout-001.zip', 'AAA');
            setInputFiles([file]);

            global.fetch = jest.fn()
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) })
                .mockResolvedValueOnce({
                    ok: true,
                    json: async () => [{ filename: 'takeout-001.zip', chunkIndex: 0, hashOfFirstChunk: 'hash-different' }],
                })
                .mockResolvedValueOnce({ ok: true }) // upload complet du fichier, depuis 0
                .mockResolvedValueOnce({ ok: true, json: async () => ({ id: 'import-existing', status: 'pending' }) }) // start
                .mockResolvedValueOnce({ ok: true, json: async () => ({ status: 'pending', processedCount: 0, totalMediaCount: null }) });

            const button = document.querySelector('[data-takeout-import-target="submit"]');
            button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 50));

            const calls = global.fetch.mock.calls.map((c) => c[0]);
            expect(calls).toContain('/api/v1/takeout-imports/import-existing/files');
        });
    });
});
