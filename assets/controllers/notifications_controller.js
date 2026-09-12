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
        // Le lien d'un message direct ne pointe vers aucune page réelle
        // (pas de vue de détail) — seule l'action de lecture est utile ici.
        event.preventDefault();

        const id = event.params.id;
        const item = event.currentTarget;

        fetch(`/direct-messages/${id}/read`, { method: 'POST' })
            .then(() => {
                item.removeAttribute('data-action');
                item.classList.add('hc-notif-item--removing');
                item.addEventListener('transitionend', () => item.remove(), { once: true });
            })
            .catch(() => {});
    }
}
