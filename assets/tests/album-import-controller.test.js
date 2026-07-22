import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import AlbumImportController from '../controllers/album_import_controller.js';

/**
 * TDD RED — #339 : l'import direct dans un album émet hc:files-selected
 * (avec albumId) au lieu de soumettre un formulaire natif — nécessaire pour
 * bénéficier de la progress bar (#336) et du traitement synchrone
 * (processSync) qui permet l'association immédiate à l'album.
 */
describe('album-import controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="album-import"
                  data-album-import-album-id-value="album-42">
                <input type="file" multiple data-album-import-target="input"
                       data-action="change->album-import#submit">
                <button type="button" data-action="click->album-import#pick">Importer</button>
            </form>
        `;

        application = Application.start();
        application.register('album-import', AlbumImportController);
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
    });

    function getInput() {
        return document.querySelector('[data-album-import-target="input"]');
    }

    test('la sélection de fichiers émet hc:files-selected avec albumId, pas de soumission de formulaire native', async () => {
        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });
        const input = getInput();
        Object.defineProperty(input, 'files', { value: [file], configurable: true });

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        const formSubmitSpy = jest.fn();
        input.closest('form').requestSubmit = formSubmitSpy;

        input.dispatchEvent(new Event('change', { bubbles: true }));
        await Promise.resolve();

        expect(received).toHaveBeenCalledTimes(1);
        const { files, albumId } = received.mock.calls[0][0].detail;
        expect(albumId).toBe('album-42');
        expect(files).toHaveLength(1);
        expect(files[0]).toBe(file);
        expect(formSubmitSpy).not.toHaveBeenCalled();
    });

    test('sélection vide : aucun événement émis', async () => {
        const input = getInput();
        Object.defineProperty(input, 'files', { value: [], configurable: true });

        const received = jest.fn();
        document.addEventListener('hc:files-selected', received);

        input.dispatchEvent(new Event('change', { bubbles: true }));
        await Promise.resolve();

        expect(received).not.toHaveBeenCalled();
    });
});
