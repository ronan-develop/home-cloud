import { Controller } from '@hotwired/stimulus';
import { annotateWebkitRelativePaths } from '../js/directory-entry-reader.js';

/* Bouton "Nouveau" (sidebar) : menu déroulant positionné dynamiquement sous
 * le bouton (fixed, hors du stacking context backdrop-filter de la sidebar).
 * Émet new-menu:open-folder-modal et hc:files-selected, consommés par
 * new_folder_modal_controller.js et assets/js/upload-modal.js. */
export default class extends Controller {
    static targets = ['button', 'menu', 'newFolderButton', 'fileInput', 'folderInput'];

    connect() {
        this._onDocumentClick = (e) => {
            if (!this.buttonTarget.contains(e.target) && !this.menuTarget.contains(e.target)) {
                this.closeMenu();
            }
        };
        document.addEventListener('click', this._onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen() ? this.closeMenu() : this.openMenu();
    }

    openMenu() {
        const rect = this.buttonTarget.getBoundingClientRect();
        this.menuTarget.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        this.menuTarget.style.left = rect.left + 'px';
        this.menuTarget.style.width = rect.width + 'px';
        this.menuTarget.style.display = 'block';
    }

    closeMenu() {
        this.menuTarget.style.display = 'none';
    }

    isOpen() {
        return this.menuTarget.style.display !== 'none';
    }

    openFolderModal() {
        this.closeMenu();
        document.dispatchEvent(new CustomEvent('new-menu:open-folder-modal'));
    }

    filesSelected() {
        const files = Array.from(this.fileInputTarget.files);
        this.fileInputTarget.value = '';
        if (files.length === 0) return;
        this.closeMenu();
        document.dispatchEvent(new CustomEvent('hc:files-selected', { detail: { files } }));
    }

    folderSelected() {
        const files = Array.from(this.folderInputTarget.files);
        this.folderInputTarget.value = '';
        this.closeMenu();
        if (files.length === 0) return;
        annotateWebkitRelativePaths(files);
        document.dispatchEvent(new CustomEvent('hc:files-selected', { detail: { files } }));
    }
}
