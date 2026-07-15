import { Controller } from '@hotwired/stimulus';

/* Ouverture/fermeture générique d'une modale : bouton déclencheur externe
 * (par id, car hors du scope du contrôleur), bouton de fermeture, clic sur
 * l'overlay, touche Échap. Réutilisable par toute modale qui suit le pattern
 * hidden + display:flex (ShareModal, futures modales similaires). */
export default class extends Controller {
    static values = { openButtonId: String };

    connect() {
        this._onKeydown = (e) => {
            if (e.key === 'Escape' && this.isOpen()) this.close();
        };
        document.addEventListener('keydown', this._onKeydown);

        const openBtn = document.getElementById(this.openButtonIdValue);
        if (openBtn) openBtn.addEventListener('click', () => this.open());
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
    }

    open() {
        this.element.classList.remove('hidden');
        this.element.style.display = 'flex';
    }

    close() {
        this.element.classList.add('hidden');
        this.element.style.display = 'none';
    }

    closeOnOverlayClick(event) {
        if (event.target === this.element) this.close();
    }

    isOpen() {
        return this.element.style.display === 'flex';
    }
}
