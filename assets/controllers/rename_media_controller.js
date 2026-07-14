import { Controller } from '@hotwired/stimulus';

/* Renommer un média en place, sans navigation : demande le nouveau nom via
 * prompt() natif (cohérent avec confirm() déjà utilisé pour la suppression),
 * envoie la requête en arrière-plan et met à jour le nom affiché au succès. */
export default class extends Controller {
    static targets = ['caption'];
    static values = { url: String, currentName: String, csrfToken: String };

    async prompt() {
        const name = window.prompt('Nouveau nom du fichier', this.currentNameValue);
        if (!name || name === this.currentNameValue) return;

        const body = new URLSearchParams({ name, _token: this.csrfTokenValue });
        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: body.toString(),
        });

        if (!response.ok) {
            window.alert('Le renommage a échoué. Vérifiez que le nom est valide.');
            return;
        }

        const data = await response.json();
        this.currentNameValue = data.name;
        if (this.hasCaptionTarget) {
            this.captionTarget.textContent = data.name;
        }
    }
}
