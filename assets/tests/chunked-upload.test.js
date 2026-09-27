import { jest, describe, test, expect } from '@jest/globals';
import { uploadFileInChunks, CHUNK_SIZE_BYTES } from '../js/chunked-upload.js';

/**
 * TDD RED → GREEN (#466) : un ZIP Google Photos Takeout peut dépasser 512M
 * (plafond dur de l'hébergement mutualisé o2switch, non contournable côté
 * serveur — confirmé en conditions réelles avec des archives ~2GB). Ce
 * module découpe un File en tranches envoyées séquentiellement, indépendant
 * du DOM/Stimulus pour rester testable en isolation.
 */
describe('uploadFileInChunks', () => {
    function makeFile(content, name = 'takeout-001.zip') {
        return new File([content], name, { type: 'application/zip' });
    }

    test('un fichier plus petit que la taille de chunk part en un seul appel', async () => {
        const file = makeFile('contenu court');
        const uploadChunk = jest.fn().mockResolvedValue(undefined);

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 1000 });

        expect(uploadChunk).toHaveBeenCalledTimes(1);
        const [blob, meta] = uploadChunk.mock.calls[0];
        expect(blob.size).toBe(file.size);
        expect(meta).toEqual({ filename: 'takeout-001.zip', chunkIndex: 0, totalChunks: 1 });
    });

    test('découpe un fichier plus gros que la taille de chunk en plusieurs tranches séquentielles', async () => {
        const file = makeFile('A'.repeat(25)); // 25 octets
        const uploadChunk = jest.fn().mockResolvedValue(undefined);

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10 });

        // 25 octets / 10 = 3 chunks (10 + 10 + 5)
        expect(uploadChunk).toHaveBeenCalledTimes(3);
        expect(uploadChunk.mock.calls[0][1]).toEqual({ filename: 'takeout-001.zip', chunkIndex: 0, totalChunks: 3 });
        expect(uploadChunk.mock.calls[1][1]).toEqual({ filename: 'takeout-001.zip', chunkIndex: 1, totalChunks: 3 });
        expect(uploadChunk.mock.calls[2][1]).toEqual({ filename: 'takeout-001.zip', chunkIndex: 2, totalChunks: 3 });
        expect(uploadChunk.mock.calls[0][0].size).toBe(10);
        expect(uploadChunk.mock.calls[2][0].size).toBe(5);
    });

    test('envoie les chunks dans l\'ordre, en attendant chaque réponse avant le suivant', async () => {
        const file = makeFile('A'.repeat(20));
        const order = [];
        const uploadChunk = jest.fn().mockImplementation(async (blob, meta) => {
            order.push(`start-${meta.chunkIndex}`);
            await Promise.resolve();
            order.push(`end-${meta.chunkIndex}`);
        });

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10 });

        expect(order).toEqual(['start-0', 'end-0', 'start-1', 'end-1']);
    });

    test('propage l\'erreur si un chunk échoue, sans envoyer les suivants', async () => {
        const file = makeFile('A'.repeat(20));
        const uploadChunk = jest.fn()
            .mockResolvedValueOnce(undefined)
            .mockRejectedValueOnce(new Error('413'));

        await expect(uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10 })).rejects.toThrow('413');
        expect(uploadChunk).toHaveBeenCalledTimes(2);
    });

    test('exporte une taille de chunk par défaut raisonnable (bien sous 512M)', () => {
        expect(CHUNK_SIZE_BYTES).toBeGreaterThan(0);
        expect(CHUNK_SIZE_BYTES).toBeLessThan(512 * 1024 * 1024);
    });
});
