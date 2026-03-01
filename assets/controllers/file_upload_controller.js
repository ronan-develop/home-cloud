import { Controller } from '@hotwired/stimulus';

/**
 * Gère la zone de drag & drop pour l'upload de fichiers.
 * Les listeners drag sont branchés directement (plus fiable que data-action).
 */
export default class extends Controller {
    static targets = ['input', 'idle', 'ready', 'filename'];
    static values  = { folderId: String };

    connect() {
        // 1. Bloquer le comportement natif du navigateur sur toute la page
        this._blockBrowser = (e) => e.preventDefault();
        document.addEventListener('dragover', this._blockBrowser);
        document.addEventListener('drop', this._blockBrowser);

        // 2. Brancher les handlers directement sur la zone
        this._onDragEnter = this._handleDragEnter.bind(this);
        this._onDragOver  = this._handleDragOver.bind(this);
        this._onDragLeave = this._handleDragLeave.bind(this);
        this._onDrop      = this._handleDrop.bind(this);

        this.element.addEventListener('dragenter', this._onDragEnter);
        this.element.addEventListener('dragover',  this._onDragOver);
        this.element.addEventListener('dragleave', this._onDragLeave);
        this.element.addEventListener('drop',      this._onDrop);
    }

    disconnect() {
        document.removeEventListener('dragover', this._blockBrowser);
        document.removeEventListener('drop', this._blockBrowser);
        this.element.removeEventListener('dragenter', this._onDragEnter);
        this.element.removeEventListener('dragover',  this._onDragOver);
        this.element.removeEventListener('dragleave', this._onDragLeave);
        this.element.removeEventListener('drop',      this._onDrop);
    }

    // Action Stimulus pour le bouton "parcourir"
    browse() {
        this.inputTarget.click();
    }

    fileSelected() {
        const file = this.inputTarget.files[0];
        if (file) this._showReady(file.name);
    }

    reset() {
        this.inputTarget.value = '';
        this.idleTarget.classList.remove('hidden');
        this.readyTarget.classList.add('hidden');
        this.element.classList.remove('drop-active');
    }

    // --- Handlers internes ---

    _handleDragEnter(e) {
        e.preventDefault();
        e.stopPropagation();
        this.element.classList.add('drop-active');
    }

    _handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'copy';
    }

    _handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!this.element.contains(e.relatedTarget)) {
            this.element.classList.remove('drop-active');
        }
    }

    _handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.element.classList.remove('drop-active');

        const file = e.dataTransfer && e.dataTransfer.files[0];
        if (!file) return;

        const dt = new DataTransfer();
        dt.items.add(file);
        this.inputTarget.files = dt.files;
        this._showReady(file.name);
    }

    _showReady(name) {
        this.filenameTarget.textContent = '\uD83D\uDCCE ' + name;
        this.idleTarget.classList.add('hidden');
        this.readyTarget.classList.remove('hidden');
    }
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
