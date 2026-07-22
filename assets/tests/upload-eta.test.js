import { describe, test, expect } from '@jest/globals';
import { createEtaTracker, formatSpeed, formatEta } from '../js/upload-eta.js';

describe('createEtaTracker', () => {
    test('un seul échantillon : vitesse nulle, ETA inconnu (pas encore de delta temporel)', () => {
        let now = 0;
        const tracker = createEtaTracker({ nowFn: () => now });

        const { speedBytesPerSec, etaSeconds } = tracker.sample(100, 1000);
        expect(speedBytesPerSec).toBe(0);
        expect(etaSeconds).toBeNull();
    });

    test('deux échantillons espacés d\'1s : vitesse moyenne et ETA cohérents', () => {
        let now = 0;
        const tracker = createEtaTracker({ nowFn: () => now });

        tracker.sample(0, 1000);
        now = 1000;
        const { speedBytesPerSec, etaSeconds } = tracker.sample(100, 1000);

        expect(speedBytesPerSec).toBe(100);
        expect(etaSeconds).toBeCloseTo(9, 5); // (1000-100)/100
    });

    test('moyenne glissante : ne garde que les derniers échantillons (fenêtre bornée)', () => {
        let now = 0;
        const tracker = createEtaTracker({ nowFn: () => now });

        // 11 échantillons à vitesse constante de 50 octets/s (1 par seconde)
        let last;
        for (let i = 0; i <= 10; i++) {
            now = i * 1000;
            last = tracker.sample(i * 50, 10000);
        }

        expect(last.speedBytesPerSec).toBeCloseTo(50, 5);
    });
});

describe('formatSpeed', () => {
    test('formate en Mo/s avec une décimale', () => {
        expect(formatSpeed(1024 * 1024)).toBe('1.0 Mo/s');
        expect(formatSpeed(2.5 * 1024 * 1024)).toBe('2.5 Mo/s');
    });
});

describe('formatEta', () => {
    test('moins de 60s : affiche en secondes', () => {
        expect(formatEta(42)).toBe('42s restantes');
    });

    test('60s ou plus : affiche en minutes + secondes', () => {
        expect(formatEta(125)).toBe('2min 5s restantes');
    });

    test('ETA null ou invalide : message générique', () => {
        expect(formatEta(null)).toBe('temps restant inconnu');
        expect(formatEta(-5)).toBe('temps restant inconnu');
        expect(formatEta(NaN)).toBe('temps restant inconnu');
    });
});
