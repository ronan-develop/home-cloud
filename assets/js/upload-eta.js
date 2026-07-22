/**
 * HomeCloud — Vitesse d'upload et temps restant estimé (#336).
 *
 * Module pur (aucune dépendance au DOM) : calcule une vitesse moyenne
 * glissante à partir d'échantillons {loaded, timestamp} successifs, puis
 * en déduit un ETA. `nowFn` injectable pour des tests déterministes
 * (cohérent avec le pattern de upload-batch.js).
 */

const MAX_SAMPLES = 5;

/**
 * Crée un tracker de vitesse pour un upload donné.
 *
 * @param {Object} [options]
 * @param {() => number} [options.nowFn=Date.now]
 * @returns {{ sample: (loaded: number) => { speedBytesPerSec: number, etaSeconds: number|null } }}
 */
export function createEtaTracker({ nowFn = () => Date.now() } = {}) {
    const samples = [];

    return {
        /**
         * Enregistre un nouvel échantillon de progression et retourne la
         * vitesse moyenne glissante + l'ETA courants.
         *
         * @param {number} loaded - octets transférés cumulés
         * @param {number} total - taille totale en octets
         * @returns {{ speedBytesPerSec: number, etaSeconds: number|null }}
         */
        sample(loaded, total) {
            const timestamp = nowFn();
            samples.push({ loaded, timestamp });
            if (samples.length > MAX_SAMPLES) samples.shift();

            const first = samples[0];
            const last = samples[samples.length - 1];
            const elapsedSec = (last.timestamp - first.timestamp) / 1000;
            const deltaBytes = last.loaded - first.loaded;

            const speedBytesPerSec = elapsedSec > 0 ? deltaBytes / elapsedSec : 0;
            const remainingBytes = total - loaded;
            const etaSeconds = speedBytesPerSec > 0 ? remainingBytes / speedBytesPerSec : null;

            return { speedBytesPerSec, etaSeconds };
        },
    };
}

/**
 * Formatte une vitesse en Mo/s.
 *
 * @param {number} bytesPerSec
 * @returns {string}
 */
export function formatSpeed(bytesPerSec) {
    const mbPerSec = bytesPerSec / (1024 * 1024);
    return `${mbPerSec.toFixed(1)} Mo/s`;
}

/**
 * Formatte un temps restant estimé en secondes ou minutes.
 *
 * @param {number|null} etaSeconds
 * @returns {string}
 */
export function formatEta(etaSeconds) {
    if (etaSeconds === null || !Number.isFinite(etaSeconds) || etaSeconds < 0) {
        return 'temps restant inconnu';
    }
    if (etaSeconds < 60) {
        return `${Math.ceil(etaSeconds)}s restantes`;
    }
    const minutes = Math.floor(etaSeconds / 60);
    const seconds = Math.round(etaSeconds % 60);
    return `${minutes}min ${seconds}s restantes`;
}
