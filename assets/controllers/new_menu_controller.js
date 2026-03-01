import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'fileInput', 'folderInput'];

    connect() {
        this._onDocumentClick = this._handleDocumentClick.bind(this);
        document.addEventListener('click', this._onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
    }

    toggle(event) {
        event.stopPropagation();
        const menu = this.menuTarget;
        if (menu.classList.contains('hidden')) {
            this._positionMenu();
            menu.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
        }
    }

    openFolderModal(event) {
        event.stopPropagation();
        this.menuTarget.classList.add('hidden');
        document.dispatchEvent(new CustomEvent('new-menu:open-folder-modal'));
    }

    fileSelected() {
        const file = this.fileInputTarget.files[0];
        this.fileInputTarget.value = '';
        if (!file) return;
        this.menuTarget.classList.add('hidden');
        document.dispatchEvent(new CustomEvent('new-menu:file-selected', { detail: { file } }));
    }

    folderSelected() {
        this.folderInputTarget.value = '';
        this.menuTarget.classList.add('hidden');
        alert('Folder import — coming soon 🚧');
    }

    // ── Private ────────────────────────────────────────────────────────

    _positionMenu() {
        const btn  = this.element.querySelector('[data-testid="new-btn"]');
        const menu = this.menuTarget;
        const rect = btn.getBoundingClientRect();
        menu.style.top   = (rect.bottom + 4) + 'px';
        menu.style.left  = rect.left + 'px';
        menu.style.width = rect.width + 'px';
    }

    _handleDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
        }
    }
}
