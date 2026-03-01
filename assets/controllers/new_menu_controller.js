import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    connect() {
        this._menu        = document.getElementById('new-menu');
        this._fileInput   = document.getElementById('new-menu-file-input');
        this._folderInput = document.getElementById('new-menu-folder-input');
        this._folderBtn   = document.getElementById('new-menu-new-folder-btn');

        this._onDocumentClick = this._handleDocumentClick.bind(this);
        document.addEventListener('click', this._onDocumentClick);

        this._fileInput.addEventListener('change', () => this._fileSelected());
        this._folderInput.addEventListener('change', () => this._folderSelected());
        this._folderBtn.addEventListener('click', () => this._openFolderModal());
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
    }

    toggle(event) {
        event.stopPropagation();
        if (this._menu.classList.contains('hidden')) {
            this._positionMenu();
            this._menu.classList.remove('hidden');
        } else {
            this._menu.classList.add('hidden');
        }
    }

    // ── Private ────────────────────────────────────────────────────────

    _openFolderModal() {
        this._menu.classList.add('hidden');
        document.dispatchEvent(new CustomEvent('new-menu:open-folder-modal'));
    }

    _fileSelected() {
        const file = this._fileInput.files[0];
        this._fileInput.value = '';
        if (!file) return;
        this._menu.classList.add('hidden');
        document.dispatchEvent(new CustomEvent('new-menu:file-selected', { detail: { file } }));
    }

    _folderSelected() {
        this._folderInput.value = '';
        this._menu.classList.add('hidden');
        alert('Folder import — coming soon 🚧');
    }

    _positionMenu() {
        const rect = this.element.getBoundingClientRect();
        this._menu.style.top   = (rect.bottom + 4) + 'px';
        this._menu.style.left  = rect.left + 'px';
        this._menu.style.width = rect.width + 'px';
    }

    _handleDocumentClick(event) {
        if (!this.element.contains(event.target) && !this._menu.contains(event.target)) {
            this._menu.classList.add('hidden');
        }
    }
}
