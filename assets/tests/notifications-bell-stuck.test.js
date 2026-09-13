import { jest, describe, test, expect, beforeEach, afterEach } from '@jest/globals';
import { Application } from '@hotwired/stimulus';
import NotificationsController from '../controllers/notifications_controller.js';

/**
 * TDD RED — bug signalé en prod (2026-09-13) : après un clic sur une entrée
 * changelog (target="_blank", ouvre un nouvel onglet + marque lu en fond),
 * la cloche redevient inutilisable au retour sur l'onglet d'origine.
 *
 * Piste : `_removeAfter` attend l'événement `transitionend` pour retirer
 * l'item du DOM (assets/styles/notifications.css). Un navigateur suspend
 * les transitions CSS d'un onglet en arrière-plan — `transitionend` ne se
 * déclenche donc jamais tant que l'utilisateur reste sur le nouvel onglet,
 * et l'item reste indéfiniment au DOM avec sa classe `--removing`.
 */
describe('notifications controller — cloche après clic sur une entrée changelog', () => {
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
                       data-notifications-target="item">Entrée changelog</a>
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

    function getButton() {
        return document.querySelector('[data-notifications-target="button"]');
    }

    function getPanel() {
        return document.querySelector('[data-notifications-target="panel"]');
    }

    test('la cloche rouvre le panel après un clic sur une entrée changelog, même sans transitionend', async () => {
        const button = getButton();
        const panel = getPanel();
        const item = document.querySelector('[data-notifications-target="item"]');

        // 1. Ouvrir le panel
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(panel.style.display).toBe('block');

        // 2. Cliquer sur l'entrée changelog : le fetch de marquage part,
        //    mais on ne simule PAS transitionend (onglet d'arrière-plan).
        item.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await Promise.resolve();
        await Promise.resolve();

        // 3. Un clic ailleurs sur le document (retour sur l'onglet d'origine,
        //    équivalent à un clic de focus) ferme le panel normalement.
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(panel.style.display).toBe('none');

        // 4. La cloche doit pouvoir rouvrir le panel — c'est ce qui casse en
        //    prod : sans ce fix, cette réouverture échoue ou ne montre rien.
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(panel.style.display).toBe('block');
    });

    test('un clic sur target="_blank" ne remonte jamais comme "click" document — le panel reste ouvert indéfiniment (position:fixed, z-index:9999 par-dessus la cloche)', async () => {
        const button = getButton();
        const panel = getPanel();
        const item = document.querySelector('[data-notifications-target="item"]');

        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(panel.style.display).toBe('block');

        // En navigateur réel, cliquer sur un lien target="_blank" ouvre un
        // nouvel onglet et ne déclenche PAS de "click" sur document dans
        // l'onglet d'origine (le focus change immédiatement) : jsdom, lui,
        // fait quand même bouillonner l'événement jusqu'à document — donc ce
        // test doit dispatcher SEULEMENT sur l'item, sans jamais simuler de
        // clic document ensuite, pour refléter fidèlement le comportement
        // réel où _onDocumentClick n'a jamais l'occasion de fermer le panel.
        item.dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        // Comportement attendu : cliquer sur une entrée doit refermer le
        // panel immédiatement (l'action est "consommée"), sans dépendre
        // d'un "click" document qui ne viendra jamais pour un lien
        // target="_blank". Échoue tant que la fermeture n'est pas explicite
        // dans markRead()/_removeAfter() — c'est le bug réel signalé en prod.
        expect(panel.style.display).toBe('none');
    });

    test('l\'item reste retiré du DOM même si transitionend ne se déclenche jamais (onglet en arrière-plan)', async () => {
        jest.useFakeTimers();
        const button = getButton();
        const item = document.querySelector('[data-notifications-target="item"]');

        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        item.dispatchEvent(new MouseEvent('click', { bubbles: false }));
        await Promise.resolve();
        await Promise.resolve();

        // Un navigateur suspend les transitions CSS d'un onglet en
        // arrière-plan : transitionend ne se déclenche jamais tant qu'on
        // reste sur le nouvel onglet ouvert par target="_blank". Sans filet
        // de sécurité, l'item reste bloqué au DOM indéfiniment avec sa
        // classe --removing, sans jamais disparaître visuellement.
        jest.advanceTimersByTime(5000);

        expect(document.querySelector('[data-notifications-target="item"]')).toBeNull();
        jest.useRealTimers();
    });
});
