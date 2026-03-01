import { Controller } from '@hotwired/stimulus';

/**
 * Gère la zone de drag & drop pour l'upload de fichiers.
 * Cible le formulaire caché et soumet quand un fichier est sélectionné.
 */
export default class extends Controller {
    static targets = ['form', 'input', 'idle', 'ready', 'filename'];
    static values  = { folderId: String };

    connect() {
        this.element.classList.remove('border-blue-400', 'bg-blue-50/40');
    }

    browse() {
        this.inputTarget.click();
    }

    fileSelected() {
        const file = this.inputTarget.files[0];
        if (file) this.#showReady(file.name);
    }

    dragOver(event) {
        event.preventDefault();
        this.element.classList.add('border-blue-400', 'bg-blue-50/40', 'dark:border-blue-600');
    }

    dragLeave() {
        this.element.classList.remove('border-blue-400', 'bg-blue-50/40', 'dark:border-blue-600');
    }

    drop(event) {
        event.preventDefault();
        this.dragLeave();
        const file = event.dataTransfer?.files[0];
        if (!file) return;

        // Injecter le fichier dans l'input caché et afficher l'état "ready"
        const dt = new DataTransfer();
        dt.items.add(file);
        this.inputTarget.files = dt.files;
        this.#showReady(file.name);
    }

    reset() {
        this.inputTarget.value = '';
        this.idleTarget.classList.remove('hidden');
        this.readyTarget.classList.add('hidden');
    }

    #showReady(name) {
        this.filenameTarget.textContent = `📎 ${name}`;
        this.idleTarget.classList.add('hidden');
        this.readyTarget.classList.remove('hidden');
    }
}
