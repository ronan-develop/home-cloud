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
                    item.addEventListener('transitionend', () => item.remove(), { once: true });
                });
            })
            .catch(() => {});
    }
}
