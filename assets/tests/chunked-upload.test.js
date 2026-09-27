import { jest, describe, test, expect } from '@jest/globals';
import { uploadFileInChunks, hashChunk, CHUNK_SIZE_BYTES } from '../js/chunked-upload.js';

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

    test('appelle onChunkUploaded après chaque chunk envoyé avec succès', async () => {
        const file = makeFile('A'.repeat(25));
        const uploadChunk = jest.fn().mockResolvedValue(undefined);
        const onChunkUploaded = jest.fn();

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10, onChunkUploaded });

        expect(onChunkUploaded).toHaveBeenCalledTimes(3);
        expect(onChunkUploaded).toHaveBeenNthCalledWith(1, { chunkIndex: 0, totalChunks: 3 });
        expect(onChunkUploaded).toHaveBeenNthCalledWith(3, { chunkIndex: 2, totalChunks: 3 });
    });

    test('n\'appelle pas onChunkUploaded pour le chunk qui échoue', async () => {
        const file = makeFile('A'.repeat(20));
        const uploadChunk = jest.fn()
            .mockResolvedValueOnce(undefined)
            .mockRejectedValueOnce(new Error('413'));
        const onChunkUploaded = jest.fn();

        await expect(
            uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10, onChunkUploaded }),
        ).rejects.toThrow('413');

        expect(onChunkUploaded).toHaveBeenCalledTimes(1);
    });

    // #481 (reprise après fermeture d'onglet) : resumeFromChunkIndex permet
    // de reprendre un fichier déjà partiellement uploadé sans renvoyer les
    // chunks déjà confirmés par le serveur.
    test('reprend à resumeFromChunkIndex au lieu de renvoyer depuis 0', async () => {
        const file = makeFile('A'.repeat(25));
        const uploadChunk = jest.fn().mockResolvedValue(undefined);

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10, resumeFromChunkIndex: 1 });

        // 3 chunks au total (25/10), les chunks 1 et 2 seulement sont envoyés.
        expect(uploadChunk).toHaveBeenCalledTimes(2);
        expect(uploadChunk.mock.calls[0][1]).toEqual({ filename: 'takeout-001.zip', chunkIndex: 1, totalChunks: 3 });
        expect(uploadChunk.mock.calls[1][1]).toEqual({ filename: 'takeout-001.zip', chunkIndex: 2, totalChunks: 3 });
    });

    test('sans resumeFromChunkIndex, envoie depuis 0 comme avant (comportement par défaut inchangé)', async () => {
        const file = makeFile('A'.repeat(15));
        const uploadChunk = jest.fn().mockResolvedValue(undefined);

        await uploadFileInChunks(file, uploadChunk, { chunkSizeBytes: 10 });

        expect(uploadChunk.mock.calls[0][1].chunkIndex).toBe(0);
    });
});

describe('hashChunk', () => {
    test('calcule un hash SHA-256 hexadécimal du contenu du blob', async () => {
        const blob = new Blob(['AAA']);

        const hash = await hashChunk(blob);

        // Référence : php -r "echo hash('sha256', 'AAA');"
        expect(hash).toBe('cb1ad2119d8fafb69566510ee712661f9f14b83385006ef92aec47f523a38358');
    });

    test('produit des hashs différents pour des contenus différents', async () => {
        const hashA = await hashChunk(new Blob(['AAA']));
        const hashB = await hashChunk(new Blob(['BBB']));

        expect(hashA).not.toBe(hashB);
    });
});
