import { Controller } from '@hotwired/stimulus';

/* Bouton "Importer" du dashboard : mêmes fichiers, même événement que le
 * menu "Nouveau" de la sidebar (new_menu_controller.js), consommé par
 * assets/js/upload-modal.js. */
export default class extends Controller {
    filesSelected(event) {
        const files = Array.from(event.target.files);
        event.target.value = '';
        if (files.length === 0) return;
        document.dispatchEvent(new CustomEvent('hc:files-selected', { detail: { files } }));
    }
}
