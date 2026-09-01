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
        const id = event.params.id;
        const item = event.currentTarget;

        fetch(`/direct-messages/${id}/read`, { method: 'POST' })
            .then(() => {
                item.classList.remove('hc-notif-item--unread');
                item.removeAttribute('data-action');
            })
            .catch(() => {});
    }
}
