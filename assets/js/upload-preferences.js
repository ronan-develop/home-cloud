const STORAGE_KEY = 'hc.upload.maxConcurrent';

export const MAX_CONCURRENT_MIN = 1;
export const MAX_CONCURRENT_MAX = 6;
export const MAX_CONCURRENT_DEFAULT = 3;

export function getMaxConcurrent() {
    const stored = localStorage.getItem(STORAGE_KEY);
    const parsed = Number(stored);

    if (stored === null || !Number.isFinite(parsed) || parsed < MAX_CONCURRENT_MIN || parsed > MAX_CONCURRENT_MAX) {
        return MAX_CONCURRENT_DEFAULT;
    }

    return Math.round(parsed);
}

export function setMaxConcurrent(value) {
    const clamped = Math.min(MAX_CONCURRENT_MAX, Math.max(MAX_CONCURRENT_MIN, Math.round(value)));
    localStorage.setItem(STORAGE_KEY, String(clamped));
}
