import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import NotificationsController from '../controllers/notifications_controller.js';

/**
 * #533 : "Tout marquer comme lu" — un clic vide le panel entier et retire
 * le badge, sans devoir marquer chaque item un par un.
 */
describe('notifications controller (#533)', () => {
    let application;

    function html() {
        document.body.innerHTML = `
            <div data-controller="notifications">
                <button data-notifications-target="button" data-action="click->notifications#toggle"></button>
                <span data-notifications-target="badge">2</span>
                <div data-notifications-target="panel">
                    <button data-action="click->notifications#markAllRead">Tout marquer comme lu</button>
                    <a href="/x" data-notifications-target="item" data-notifications-type-param="direct_message" data-notifications-id-param="1">Item 1</a>
                    <a href="/y" data-notifications-target="item" data-notifications-type-param="changelog">Item 2</a>
                </div>
            </div>
        `;
    }

    beforeEach(() => {
        html();
        application = Application.start();
        application.register('notifications', NotificationsController);
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('appelle /notifications/mark-all-read et retire tous les items du panel', async () => {
        global.fetch = jest.fn().mockResolvedValue({ ok: true });

        const button = document.querySelector('[data-action="click->notifications#markAllRead"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(global.fetch).toHaveBeenCalledWith('/notifications/mark-all-read', { method: 'POST' });

        await new Promise((resolve) => setTimeout(resolve, 0));

        const items = document.querySelectorAll('[data-notifications-target="item"]');
        items.forEach((item) => expect(item.classList.contains('hc-notif-item--removing')).toBe(true));
    });

    test('retire le badge immédiatement', () => {
        global.fetch = jest.fn().mockResolvedValue({ ok: true });

        const button = document.querySelector('[data-action="click->notifications#markAllRead"]');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(document.querySelector('[data-notifications-target="badge"]')).toBeNull();
    });
});
