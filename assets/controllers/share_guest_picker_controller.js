import { Controller } from '@hotwired/stimulus';

/* Sélection directe d'un invité déjà créé dans ShareModal : ajoute son email
 * au champ texte existant (qui accepte déjà une liste séparée par virgules
 * côté serveur), sans le dupliquer si déjà présent. */
export default class extends Controller {
    static targets = ['input'];

    add(event) {
        const email = event.currentTarget.dataset.guestEmail;
        const current = this.inputTarget.value
            .split(',')
            .map((e) => e.trim())
            .filter((e) => e !== '');

        if (current.includes(email)) return;

        current.push(email);
        this.inputTarget.value = current.join(', ');
    }
}
