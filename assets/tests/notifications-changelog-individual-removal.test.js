import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import NotificationsController from '../controllers/notifications_controller.js';

/**
 * TDD RED — bug #450 signalé par l'utilisateur : "quand on lit une
 * [notification] les autres se vident aussi". Reproduit avec plusieurs
 * entrées changelog dans le panel — lastChangelogViewedAt est un timestamp
 * global (pas par entrée, cf. ticket), mais l'utilisateur n'a pas besoin de
 * préserver l'état "non lu" des autres entrées côté serveur puisqu'un clic
 * redirige de toute façon vers GitHub : seul le retrait DOM doit se limiter
 * à l'item cliqué (cf. commentaire de clarification sur le ticket #450).
 */
describe('notifications controller — retrait individuel d\'une entrée changelog (#450)', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="notifications">
                <button type="button" data-notifications-target="button"
                        data-action="click->notifications#toggle">Cloche</button>
                <div data-notifications-target="panel" style="display:none">
                    <a href="https://github.com/x/y/pull/1" target="_blank" rel="noopener"
                       data-action="click->notifications#markRead"
                       data-notifications-type-param="changelog"
                       data-notifications-target="item">Entrée changelog 1</a>
                    <a href="https://github.com/x/y/pull/2" target="_blank" rel="noopener"
                       data-action="click->notifications#markRead"
                       data-notifications-type-param="changelog"
                       data-notifications-target="item">Entrée changelog 2</a>
                    <a href="https://github.com/x/y/pull/3" target="_blank" rel="noopener"
                       data-action="click->notifications#markRead"
                       data-notifications-type-param="changelog"
                       data-notifications-target="item">Entrée changelog 3</a>
                </div>
            </div>
        `;

        global.fetch = jest.fn().mockResolvedValue({ ok: true });

        application = Application.start();
        application.register('notifications', NotificationsController);
    });

    afterEach(() => {
        application.stop();
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('cliquer sur une entrée changelog ne retire qu\'elle, les 2 autres restent visibles', async () => {
        jest.useFakeTimers();
        const items = document.querySelectorAll('[data-notifications-target="item"]');
        const clicked = items[1];

        clicked.dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        jest.advanceTimersByTime(1000);

        const remaining = document.querySelectorAll('[data-notifications-target="item"]');
        expect(remaining).toHaveLength(2);
        expect(Array.from(remaining).map((el) => el.textContent)).toEqual([
            'Entrée changelog 1',
            'Entrée changelog 3',
        ]);

        jest.useRealTimers();
    });

    test('un seul appel réseau de marquage est fait, même si plusieurs entrées changelog sont présentes', async () => {
        const items = document.querySelectorAll('[data-notifications-target="item"]');

        items[0].dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch).toHaveBeenCalledWith('/changelog/mark-viewed', { method: 'POST' });
    });
});
