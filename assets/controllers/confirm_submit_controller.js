import { Controller } from '@hotwired/stimulus';

/* Demande confirmation avant la soumission d'un formulaire destructeur.
 * Usage : data-controller="confirm-submit" data-confirm-submit-message-value="..." */
export default class extends Controller {
    static values = { message: String };

    connect() {
        this.element.addEventListener('submit', this.onSubmit.bind(this));
    }

    onSubmit(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
