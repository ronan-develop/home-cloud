import { jest, describe, test, expect } from '@jest/globals';

const { computeBatch, declareBatch, createBatchPoller } = await import('../js/upload-batch.js');

describe('computeBatch', () => {
    test('agrège nombre, taille cumulée et noms', () => {
        const files = [
            { name: 'a.jpg', size: 1000 },
            { name: 'b.nef', size: 2500 },
        ];
        expect(computeBatch(files)).toEqual({
            count: 2,
            totalSize: 3500,
            filenames: ['a.jpg', 'b.nef'],
        });
    });

    test('lot vide → zéro', () => {
        expect(computeBatch([])).toEqual({ count: 0, totalSize: 0, filenames: [] });
    });

    test('tolère une taille manquante', () => {
        expect(computeBatch([{ name: 'x' }]).totalSize).toBe(0);
    });
});

describe('declareBatch', () => {
    test('poste les métadonnées du lot et retourne la réponse serveur', async () => {
        const postJson = jest.fn().mockResolvedValue({ batchId: 'b-1', mode: 'deferred' });
        const files = [{ name: 'a.jpg', size: 10 }, { name: 'b.jpg', size: 20 }];

        const res = await declareBatch(files, postJson);

        expect(postJson).toHaveBeenCalledWith('/api/v1/uploads/batch', {
            count: 2,
            totalSize: 30,
            filenames: ['a.jpg', 'b.jpg'],
        });
        expect(res).toEqual({ batchId: 'b-1', mode: 'deferred' });
    });
});

describe('createBatchPoller', () => {
    // Ordonnanceur de timers manuel : on garde la callback et on l'exécute à la
    // demande, pour piloter le polling sans horloge réelle.
    function manualScheduler() {
        let pending = null;
        return {
            setTimeoutFn: (fn) => { pending = fn; return 1; },
            clearTimeoutFn: () => { pending = null; },
            run: async () => { const fn = pending; pending = null; if (fn) await fn(); },
            hasPending: () => pending !== null,
        };
    }

    test('appelle onComplete et s\'arrête quand le lot est completed', async () => {
        const sched = manualScheduler();
        const fetchStatus = jest.fn().mockResolvedValue({ status: 'completed', processed: 3, total: 3 });
        const onComplete = jest.fn();

        const poller = createBatchPoller({
            batchId: 'b-1', fetchStatus, onComplete,
            setTimeoutFn: sched.setTimeoutFn, clearTimeoutFn: sched.clearTimeoutFn,
        });
        poller.start();
        await sched.run(); // premier tick

        expect(onComplete).toHaveBeenCalledWith({ status: 'completed', processed: 3, total: 3 });
        expect(poller.isStopped()).toBe(true);
        expect(sched.hasPending()).toBe(false);
    });

    test('reprogramme tant que le lot n\'est pas terminé (backoff)', async () => {
        const sched = manualScheduler();
        const delays = [];
        const setTimeoutFn = (fn, d) => { delays.push(d); return sched.setTimeoutFn(fn, d); };
        const fetchStatus = jest.fn().mockResolvedValue({ status: 'processing', processed: 1, total: 3 });

        const poller = createBatchPoller({
            batchId: 'b-1', fetchStatus,
            setTimeoutFn, clearTimeoutFn: sched.clearTimeoutFn,
        });
        poller.start();
        await sched.run(); // tick 1 → reprogramme
        await sched.run(); // tick 2 → reprogramme

        expect(fetchStatus).toHaveBeenCalledTimes(2);
        expect(poller.isStopped()).toBe(false);
        // Intervalle croissant : 5s puis 10s puis 15s.
        expect(delays.slice(0, 3)).toEqual([5000, 10000, 15000]);
    });

    test('s\'arrête au-delà du timeout global', async () => {
        const sched = manualScheduler();
        let clock = 0;
        const nowFn = () => clock;
        const fetchStatus = jest.fn().mockResolvedValue({ status: 'processing' });

        const poller = createBatchPoller({
            batchId: 'b-1', fetchStatus, nowFn, timeoutMs: 1000,
            setTimeoutFn: sched.setTimeoutFn, clearTimeoutFn: sched.clearTimeoutFn,
        });
        poller.start();
        clock = 2000; // au-delà du timeout
        await sched.run();

        expect(fetchStatus).not.toHaveBeenCalled();
        expect(poller.isStopped()).toBe(true);
    });

    test('stop() empêche tout tick ultérieur', async () => {
        const sched = manualScheduler();
        const fetchStatus = jest.fn().mockResolvedValue({ status: 'processing' });

        const poller = createBatchPoller({
            batchId: 'b-1', fetchStatus,
            setTimeoutFn: sched.setTimeoutFn, clearTimeoutFn: sched.clearTimeoutFn,
        });
        poller.start();
        poller.stop();
        await sched.run();

        expect(fetchStatus).not.toHaveBeenCalled();
        expect(poller.isStopped()).toBe(true);
    });

    test('poursuit le polling malgré une erreur réseau ponctuelle', async () => {
        const sched = manualScheduler();
        const fetchStatus = jest.fn().mockRejectedValue(new Error('network'));
        const onError = jest.fn();

        const poller = createBatchPoller({
            batchId: 'b-1', fetchStatus, onError,
            setTimeoutFn: sched.setTimeoutFn, clearTimeoutFn: sched.clearTimeoutFn,
        });
        poller.start();
        await sched.run();

        expect(onError).toHaveBeenCalled();
        expect(poller.isStopped()).toBe(false);
        expect(sched.hasPending()).toBe(true); // reprogrammé
    });
});
