import { Controller } from '@hotwired/stimulus';

/**
 * Manages drag & drop + file input for multi-file uploads.
 * Dispatches 'hc:files-selected' event with selected files.
 * Triggers upload modal via custom event detail.
 */
export default class extends Controller {
    static targets = ['input', 'idle', 'ready', 'filename'];
    static values  = { folderId: String };

    connect() {
        // 1. Block native browser drag/drop behavior globally
        this._blockBrowser = (e) => e.preventDefault();
        document.addEventListener('dragover', this._blockBrowser);
        document.addEventListener('drop', this._blockBrowser);

        // 2. Attach zone-specific handlers
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

    // Action: Browse button click
    browse() {
        this.inputTarget.click();
    }

    fileSelected() {
        const files = Array.from(this.inputTarget.files);
        if (files.length === 0) return;
        this._dispatchFiles(files);
    }

    reset() {
        this.inputTarget.value = '';
        this.idleTarget.classList.remove('hidden');
        this.readyTarget.classList.add('hidden');
        this.element.classList.remove('drop-active');
    }

    // --- Internal handlers ---

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

        const files = Array.from(e.dataTransfer?.files || []);
        if (files.length === 0) return;

        this._dispatchFiles(files);
    }

    _dispatchFiles(files) {
        console.log('[FileUploadController] Dispatching', files.length, 'file(s) via hc:files-selected');
        // Dispatch custom event to trigger upload modal
        document.dispatchEvent(new CustomEvent('hc:files-selected', {
            detail: { files }
        }));

        // Update UI to show files selected
        const count = files.length;
        const plural = count > 1 ? 's' : '';
        this.filenameTarget.textContent = `📎 ${count} fichier${plural} sélectionné${plural}`;
        this.idleTarget.classList.add('hidden');
        this.readyTarget.classList.remove('hidden');
    }
}
