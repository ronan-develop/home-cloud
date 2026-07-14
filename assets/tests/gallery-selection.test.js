import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import GallerySelectionController from '../controllers/gallery_selection_controller.js';

describe('gallery-selection controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="gallery-selection" data-gallery-selection-bulk-delete-url-value="/gallery/bulk-delete" data-gallery-selection-csrf-token-value="fake-csrf-token">
                <div data-gallery-selection-target="thumb">
                    <input type="checkbox" data-gallery-selection-target="checkbox" value="media-1" data-action="change->gallery-selection#update">
                </div>
                <div data-gallery-selection-target="thumb">
                    <input type="checkbox" data-gallery-selection-target="checkbox" value="media-2" data-action="change->gallery-selection#update">
                </div>
                <div data-gallery-selection-target="bar" class="hidden">
                    <span data-gallery-selection-target="count">0 sélectionné</span>
                    <button data-action="click->gallery-selection#deleteSelected"></button>
                </div>
            </div>
        `;

        application = Application.start();
        application.register('gallery-selection', GallerySelectionController);

        global.fetch = jest.fn();
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
    });

    function checkboxes() {
        return Array.from(document.querySelectorAll('[data-gallery-selection-target="checkbox"]'));
    }

    test('does nothing when confirmation is cancelled', async () => {
        checkboxes()[0].checked = true;
        checkboxes()[0].dispatchEvent(new Event('change'));
        jest.spyOn(window, 'confirm').mockReturnValue(false);

        document.querySelector('button').click();
        await Promise.resolve();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('sends selected media ids and removes their thumbnails on success', async () => {
        checkboxes()[0].checked = true;
        checkboxes()[0].dispatchEvent(new Event('change'));
        jest.spyOn(window, 'confirm').mockReturnValue(true);
        global.fetch.mockResolvedValue({ ok: true });

        document.querySelector('button').click();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(global.fetch).toHaveBeenCalledWith('/gallery/bulk-delete', expect.objectContaining({ method: 'POST' }));
        const body = global.fetch.mock.calls[0][1].body;
        expect(body.getAll('mediaIds[]')).toEqual(['media-1']);
        expect(body.get('_token')).toBe('fake-csrf-token');
        expect(document.querySelectorAll('[data-gallery-selection-target="thumb"]').length).toBe(1);
    });

    test('alerts and keeps thumbnails on failure', async () => {
        checkboxes()[0].checked = true;
        checkboxes()[0].dispatchEvent(new Event('change'));
        jest.spyOn(window, 'confirm').mockReturnValue(true);
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch.mockResolvedValue({ ok: false });

        document.querySelector('button').click();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(alertSpy).toHaveBeenCalled();
        expect(document.querySelectorAll('[data-gallery-selection-target="thumb"]').length).toBe(2);
    });
});
