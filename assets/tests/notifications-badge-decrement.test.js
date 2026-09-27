import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import NotificationsController from '../controllers/notifications_controller.js';

/**
 * TDD RED — #468 : depuis le fix #450 (retrait individuel d'une entrée du
 * panel au clic), la liste se vide correctement mais la pastille affichant
 * le nombre de notifications non lues (rendue une fois au chargement de la
 * page, cf. NotificationDropdown.html.twig) ne décrémente jamais — elle
 * reste bloquée à sa valeur initiale jusqu'au prochain rechargement.
 */
describe('notifications controller — décrément de la pastille au clic (#468)', () => {
    let application;

    function html(initialCount) {
        document.body.innerHTML = `
            <div data-controller="notifications">
                <button type="button" data-notifications-target="button"
                        data-action="click->notifications#toggle">
                    Cloche
                    ${initialCount !== null ? `<span data-notifications-target="badge">${initialCount}</span>` : ''}
                </button>
                <div data-notifications-target="panel" style="display:none">
                    <a href="https://github.com/x/y/pull/1" target="_blank" rel="noopener"
                       data-action="click->notifications#markRead"
                       data-notifications-type-param="changelog"
                       data-notifications-target="item">Entrée changelog 1</a>
                    <a href="https://github.com/x/y/pull/2" target="_blank" rel="noopener"
                       data-action="click->notifications#markRead"
                       data-notifications-type-param="changelog"
                       data-notifications-target="item">Entrée changelog 2</a>
                </div>
            </div>
        `;
    }

    beforeEach(() => {
        html(2);
        global.fetch = jest.fn().mockResolvedValue({ ok: true });
        application = Application.start();
        application.register('notifications', NotificationsController);
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('décrémente la pastille de 1 au clic sur une entrée', async () => {
        const items = document.querySelectorAll('[data-notifications-target="item"]');
        items[0].dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        const badge = document.querySelector('[data-notifications-target="badge"]');
        expect(badge.textContent).toBe('1');
    });

    test('retire la pastille quand le compteur atteint 0', async () => {
        html(1);
        // Laisse le MutationObserver de Stimulus reconnecter le contrôleur
        // au nouveau DOM avant de simuler le clic.
        await new Promise((resolve) => setTimeout(resolve, 0));
        const items = document.querySelectorAll('[data-notifications-target="item"]');
        items[0].dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        const badge = document.querySelector('[data-notifications-target="badge"]');
        expect(badge).toBeNull();
    });

    test('ne lève pas d\'erreur si aucune pastille n\'est présente dans le DOM', async () => {
        html(null);
        const items = document.querySelectorAll('[data-notifications-target="item"]');

        expect(() => items[0].dispatchEvent(new MouseEvent('click', { bubbles: false }))).not.toThrow();
    });
});
