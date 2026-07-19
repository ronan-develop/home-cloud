/**
 * HomeCloud — Déclaration de lot d'upload et suivi de traitement (#261).
 *
 * Le multi-upload envoie une requête par fichier ; le serveur, pour raisonner
 * sur le lot entier, a besoin qu'on le lui déclare d'abord (nombre + taille
 * cumulée + noms). Il répond un batchId et un mode :
 *   - "immediate" : petit lot, traité juste après la réponse HTTP ;
 *   - "deferred"  : lot lourd, traité par le worker → on prévient l'utilisateur
 *                   et on suit l'avancement par polling court.
 *
 * Module volontairement pur (aucune dépendance au DOM) pour être testable :
 * les I/O (POST, GET, timers) sont injectées.
 */

export const BATCH_CREATE_ROUTE = '/api/v1/uploads/batch';

// Polling court (jamais de long-polling : occuperait un process PHP-FPM sur
// l'hébergement mutualisé). Intervalle croissant pour alléger encore le serveur.
export const POLL_BACKOFF_MS = [5000, 10000, 15000];
export const POLL_TIMEOUT_MS = 10 * 60 * 1000; // 10 min → l'email prend le relais

/**
 * Agrège les métadonnées d'un lot à partir des fichiers sélectionnés.
 *
 * @param {Iterable<File>} files
 * @returns {{ count: number, totalSize: number, filenames: string[] }}
 */
export function computeBatch(files) {
    const list = Array.from(files || []);
    return {
        count: list.length,
        totalSize: list.reduce((sum, f) => sum + (Number(f?.size) || 0), 0),
        filenames: list.map((f) => f?.name ?? ''),
    };
}

/**
 * Déclare le lot au serveur et retourne { batchId, mode }.
 *
 * @param {Iterable<File>} files
 * @param {(url: string, payload: object) => Promise<object>} postJson
 * @returns {Promise<{ batchId: string, mode: string }>}
 */
export async function declareBatch(files, postJson) {
    const batch = computeBatch(files);
    return postJson(BATCH_CREATE_ROUTE, batch);
}

/**
 * Poller d'avancement d'un lot, avec backoff et arrêts garantis.
 *
 * S'arrête (définitivement) au premier des cas suivants : lot `completed`,
 * `stop()` explicite (onglet caché, annulation), ou dépassement du timeout
 * global. Les timers sont injectables pour des tests déterministes.
 *
 * @returns {{ start: () => object, stop: () => void, isStopped: () => boolean }}
 */
export function createBatchPoller({
    batchId,
    fetchStatus,
    onComplete,
    onError,
    setTimeoutFn = setTimeout,
    clearTimeoutFn = clearTimeout,
    nowFn = () => Date.now(),
    backoffMs = POLL_BACKOFF_MS,
    timeoutMs = POLL_TIMEOUT_MS,
}) {
    let timer = null;
    let attempt = 0;
    let stopped = false;
    const startedAt = nowFn();

    function nextDelay() {
        return backoffMs[Math.min(attempt, backoffMs.length - 1)];
    }

    function stop() {
        stopped = true;
        if (timer !== null) {
            clearTimeoutFn(timer);
            timer = null;
        }
    }

    function schedule() {
        if (stopped) return;
        timer = setTimeoutFn(tick, nextDelay());
    }

    async function tick() {
        timer = null;
        if (stopped) return;

        if (nowFn() - startedAt >= timeoutMs) {
            stop();
            return;
        }

        try {
            const status = await fetchStatus(batchId);
            if (stopped) return;
            if (status && status.status === 'completed') {
                stop();
                onComplete?.(status);
                return;
            }
        } catch (err) {
            onError?.(err);
        }

        attempt += 1;
        schedule();
    }

    const api = {
        start() {
            schedule();
            return api;
        },
        stop,
        isStopped: () => stopped,
    };

    return api;
}
