import { Controller } from '@hotwired/stimulus';

/* Import de photos depuis le disque directement dans un album : ouvre le
 * sélecteur de fichiers natif, soumet automatiquement le formulaire dès
 * qu'une sélection est faite. */
export default class extends Controller {
    static targets = ['input'];

    pick() {
        this.inputTarget.click();
    }

    submit() {
        if (this.inputTarget.files.length === 0) return;
        this.element.requestSubmit();
    }
}
