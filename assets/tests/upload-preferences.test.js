import { describe, test, expect, beforeEach } from '@jest/globals';
import {
    getMaxConcurrent,
    setMaxConcurrent,
    MAX_CONCURRENT_MIN,
    MAX_CONCURRENT_MAX,
    MAX_CONCURRENT_DEFAULT,
} from '../js/upload-preferences.js';

describe('upload-preferences', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    describe('getMaxConcurrent', () => {
        test('retourne la valeur par défaut si rien n\'est stocké', () => {
            expect(getMaxConcurrent()).toBe(MAX_CONCURRENT_DEFAULT);
        });

        test('retourne la valeur stockée si valide', () => {
            setMaxConcurrent(5);
            expect(getMaxConcurrent()).toBe(5);
        });

        test('retombe sur la valeur par défaut si la valeur stockée est corrompue (non numérique)', () => {
            localStorage.setItem('hc.upload.maxConcurrent', 'not-a-number');
            expect(getMaxConcurrent()).toBe(MAX_CONCURRENT_DEFAULT);
        });

        test('retombe sur la valeur par défaut si la valeur stockée est hors bornes', () => {
            localStorage.setItem('hc.upload.maxConcurrent', '999');
            expect(getMaxConcurrent()).toBe(MAX_CONCURRENT_DEFAULT);
        });
    });

    describe('setMaxConcurrent', () => {
        test('persiste une valeur valide', () => {
            setMaxConcurrent(4);
            expect(localStorage.getItem('hc.upload.maxConcurrent')).toBe('4');
        });

        test('clampe une valeur trop basse au minimum', () => {
            setMaxConcurrent(0);
            expect(getMaxConcurrent()).toBe(MAX_CONCURRENT_MIN);
        });

        test('clampe une valeur trop haute au maximum', () => {
            setMaxConcurrent(50);
            expect(getMaxConcurrent()).toBe(MAX_CONCURRENT_MAX);
        });

        test('arrondit une valeur décimale', () => {
            setMaxConcurrent(3.7);
            expect(getMaxConcurrent()).toBe(4);
        });
    });
});
