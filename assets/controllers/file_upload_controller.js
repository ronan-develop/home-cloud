import { Controller } from '@hotwired/stimulus';

/**
 * Gère la zone de drag & drop pour l'upload de fichiers.
 */
export default class extends Controller {
    static targets = ['form', 'input', 'idle', 'ready', 'filename'];
    static values  = { folderId: String };

    browse() {
        this.inputTarget.click();
    }

    fileSelected() {
        const file = this.inputTarget.files[0];
        if (file) this._showReady(file.name);
    }

    dragEnter(event) {
        event.preventDefault();
        this.element.classList.add('border-blue-400', 'bg-blue-50/40');
    }

    dragOver(event) {
        event.preventDefault();
    }

    dragLeave(event) {
        // Ne retirer la classe que si on quitte vraiment le conteneur
        if (!this.element.contains(event.relatedTarget)) {
            this.element.classList.remove('border-blue-400', 'bg-blue-50/40');
        }
    }

    drop(event) {
        event.preventDefault();
        this.element.classList.remove('border-blue-400', 'bg-blue-50/40');

        const file = event.dataTransfer && event.dataTransfer.files[0];
        if (!file) return;

        const dt = new DataTransfer();
        dt.items.add(file);
        this.inputTarget.files = dt.files;
        this._showReady(file.name);
    }

    reset() {
        this.inputTarget.value = '';
        this.idleTarget.classList.remove('hidden');
        this.readyTarget.classList.add('hidden');
    }

    _showReady(name) {
        this.filenameTarget.textContent = '\uD83D\uDCCE ' + name;
        this.idleTarget.classList.add('hidden');
        this.readyTarget.classList.remove('hidden');
    }
}
