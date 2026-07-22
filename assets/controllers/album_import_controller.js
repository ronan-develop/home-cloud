import { Controller } from '@hotwired/stimulus';

/* Import de photos depuis le disque directement dans un album : ouvre le
 * sélecteur de fichiers natif, puis émet hc:files-selected (avec albumId)
 * au lieu de soumettre le formulaire nativement — consommé par
 * upload-modal.js (mode album, #339), qui offre la progress bar (#336) et
 * associe chaque média à l'album une fois uploadé. */
export default class extends Controller {
    static targets = ['input'];
    static values = { albumId: String };

    pick() {
        this.inputTarget.click();
    }

    submit() {
        const files = Array.from(this.inputTarget.files);
        this.inputTarget.value = '';
        if (files.length === 0) return;
        document.dispatchEvent(new CustomEvent('hc:files-selected', {
            detail: { files, albumId: this.albumIdValue },
        }));
    }
}
