import { describe, test, expect } from '@jest/globals';

const { buildExifItems, buildExifPanelHtml } = await import('../js/exif-panel.js');

describe('buildExifItems', () => {
    test('ne garde que les réglages présents', () => {
        const items = buildExifItems({
            takenAt: '15/06/2024 12:30',
            camera: 'NIKON Z 6',
            aperture: '2.8',
            shutter: '1/250',
            iso: '400',
            focal: '50',
            lens: 'NIKKOR Z 50mm',
        });
        expect(items).toEqual([
            { label: 'Prise le', value: '15/06/2024 12:30' },
            { label: 'Appareil', value: 'NIKON Z 6' },
            { label: 'Ouverture', value: 'f/2.8' },
            { label: 'Vitesse', value: '1/250s' },
            { label: 'ISO', value: '400' },
            { label: 'Focale', value: '50 mm' },
            { label: 'Objectif', value: 'NIKKOR Z 50mm' },
        ]);
    });

    test('champs vides ignorés', () => {
        expect(buildExifItems({ aperture: '', iso: '400' })).toEqual([
            { label: 'ISO', value: '400' },
        ]);
    });

    test('dataset vide → aucun item', () => {
        expect(buildExifItems({})).toEqual([]);
    });
});

describe('buildExifPanelHtml', () => {
    test('rend les réglages en HTML', () => {
        const html = buildExifPanelHtml({ aperture: '2.8', iso: '400' });
        expect(html).toContain('f/2.8');
        expect(html).toContain('ISO');
        expect(html).toContain('400');
    });

    test('ajoute un lien carte quand le GPS est présent', () => {
        const html = buildExifPanelHtml({ gpsLat: '48.8566', gpsLon: '2.3522' });
        expect(html).toContain('openstreetmap.org');
        expect(html).toContain('48.8566');
    });

    test('chaîne vide quand rien à montrer', () => {
        expect(buildExifPanelHtml({})).toBe('');
    });

    test('échappe le HTML des valeurs (anti-XSS)', () => {
        const html = buildExifPanelHtml({ lens: '<script>alert(1)</script>' });
        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
    });
});
