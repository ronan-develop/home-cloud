import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import AlbumReorderController from '../controllers/album_reorder_controller.js';

describe('album-reorder controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="album-reorder"
                 data-album-reorder-url-value="/albums/abc/reorder"
                 data-album-reorder-csrf-token-value="fake-csrf-token">
                <div data-album-reorder-target="item" data-media-id="media-1"></div>
                <div data-album-reorder-target="item" data-media-id="media-2"></div>
            </div>
        `;

        application = Application.start();
        application.register('album-reorder', AlbumReorderController);

        global.fetch = jest.fn().mockResolvedValue({ ok: true });
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
    });

    function getController() {
        const el = document.querySelector('[data-controller="album-reorder"]');
        return application.getControllerForElementAndIdentifier(el, 'album-reorder');
    }

    test('submits the new media order with the CSRF token on drag end', async () => {
        const controller = getController();
        controller.dragged = document.querySelectorAll('[data-album-reorder-target="item"]')[0];

        controller.dragEnd();
        await Promise.resolve();
        await Promise.resolve();

        expect(global.fetch).toHaveBeenCalledWith('/albums/abc/reorder', expect.objectContaining({ method: 'POST' }));
        const body = new URLSearchParams(global.fetch.mock.calls[0][1].body);
        expect(body.get('_token')).toBe('fake-csrf-token');
        expect(body.getAll('mediaIds[]')).toEqual(['media-1', 'media-2']);
    });

    test('does nothing if dragEnd fires without an active drag', () => {
        const controller = getController();
        controller.dragged = null;

        controller.dragEnd();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('preventDrag stops propagation so the remove button does not start a real drag', () => {
        const controller = getController();
        const event = { preventDefault: jest.fn(), stopPropagation: jest.fn() };

        controller.preventDrag(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopPropagation).toHaveBeenCalled();
    });
});
