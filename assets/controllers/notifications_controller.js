import { Controller } from '@hotwired/stimulus';

/* Cloche topbar : dropdown de la pile unifiée de notifications (messages
 * directs + changelog, #373). Modèle : new_menu_controller.js. */
export default class extends Controller {
    static targets = ['button', 'panel'];

    connect() {
        this._onDocumentClick = (e) => {
            if (!this.buttonTarget.contains(e.target) && !this.panelTarget.contains(e.target)) {
                this.closePanel();
            }
        };
        document.addEventListener('click', this._onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen() ? this.closePanel() : this.openPanel();
    }

    openPanel() {
        const rect = this.buttonTarget.getBoundingClientRect();
        this.panelTarget.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        this.panelTarget.style.right = (window.innerWidth - rect.right) + 'px';
        this.panelTarget.style.display = 'block';
    }

    closePanel() {
        this.panelTarget.style.display = 'none';
    }

    isOpen() {
        return this.panelTarget.style.display === 'block';
    }

    markRead(event) {
        const type = event.params.type;
        const item = event.currentTarget;

        // target="_blank" (entrée changelog) ouvre un nouvel onglet sans
        // jamais faire remonter de "click" sur document dans l'onglet
        // d'origine — _onDocumentClick n'a donc jamais l'occasion de fermer
        // le panel, qui reste display:block indéfiniment (position:fixed,
        // par-dessus la cloche elle-même) tant qu'on ne le referme pas ici
        // explicitement. Bug réel signalé en prod le 2026-09-13.
        this.closePanel();

        if (type === 'direct_message') {
            // Le lien d'un message direct ne pointe vers aucune page réelle
            // (pas de vue de détail) — seule l'action de lecture est utile ici.
            event.preventDefault();
            this._removeAfter(fetch(`/direct-messages/${event.params.id}/read`, { method: 'POST' }), [item]);
        } else {
            // target="_blank" ouvre la PR GitHub dans un nouvel onglet — le
            // marquage se joue en fond dans l'onglet d'origine, laissant le
            // temps de voir l'animation de retrait sans revenir sur la page.
            // lastChangelogViewedAt est global (pas par entrée) : cliquer sur
            // une entrée marque tout le changelog lu, donc toutes les entrées
            // changelog du panel doivent disparaître, pas seulement celle-ci.
            const changelogItems = Array.from(this.panelTarget.querySelectorAll('[data-notifications-type-param="changelog"]'));
            this._removeAfter(fetch('/changelog/mark-viewed', { method: 'POST' }), changelogItems);
        }
    }

    _removeAfter(fetchPromise, items) {
        fetchPromise
            .then(() => {
                items.forEach((item) => {
                    item.removeAttribute('data-action');
                    item.classList.add('hc-notif-item--removing');
                    // Filet de sécurité : un navigateur suspend les transitions
                    // CSS d'un onglet en arrière-plan (cas target="_blank" du
                    // changelog) — transitionend ne se déclenche alors jamais
                    // tant qu'on reste sur le nouvel onglet, laissant l'item
                    // bloqué au DOM indéfiniment. Le timeout couvre ce cas
                    // sans changer le comportement normal (transition ~0.2s).
                    let removed = false;
                    const remove = () => {
                        if (removed) return;
                        removed = true;
                        item.remove();
                    };
                    item.addEventListener('transitionend', remove, { once: true });
                    setTimeout(remove, 1000);
                });
            })
            .catch(() => {});
    }
}
