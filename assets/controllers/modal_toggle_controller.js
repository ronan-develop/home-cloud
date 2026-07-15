import { Controller } from '@hotwired/stimulus';

/* Ouverture/fermeture générique d'une modale : bouton déclencheur externe
 * (par id, car hors du scope du contrôleur), bouton de fermeture, clic sur
 * l'overlay, touche Échap, focus optionnel d'un champ à l'ouverture.
 * Réutilisable par toute modale qui suit le pattern hidden + display:flex
 * (ShareModal, NewAlbumModal, futures modales similaires). */
export default class extends Controller {
    static targets = ['focusOnOpen'];
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
        if (this.hasFocusOnOpenTarget) {
            setTimeout(() => this.focusOnOpenTarget.focus(), 50);
        }
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
