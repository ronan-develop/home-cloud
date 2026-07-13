import { Controller } from '@hotwired/stimulus';

/* Sélection multiple de médias dans la galerie : ajout à un album
 * (?album=...) ou suppression définitive en masse (bulkDeleteUrlValue).
 * Affiche la barre d'action flottante dès qu'au moins un média est coché. */
export default class extends Controller {
    static targets = ['thumb', 'checkbox', 'bar', 'count'];
    static values = { bulkDeleteUrl: String };

    update() {
        const checked = this.checkboxTargets.filter((cb) => cb.checked);

        this.checkboxTargets.forEach((cb) => {
            cb.closest('[data-gallery-selection-target="thumb"]')
                .classList.toggle('hc-media-thumb--selected', cb.checked);
        });

        this.countTarget.textContent = `${checked.length} sélectionné${checked.length > 1 ? 's' : ''}`;
        this.barTarget.classList.toggle('hidden', checked.length === 0);
    }

    async deleteSelected() {
        const checked = this.checkboxTargets.filter((cb) => cb.checked);
        if (checked.length === 0) return;

        const label = checked.length > 1 ? `ces ${checked.length} médias` : 'ce média';
        if (!window.confirm(`Supprimer définitivement ${label} ? Cette action est irréversible.`)) {
            return;
        }

        const body = new URLSearchParams();
        checked.forEach((cb) => body.append('mediaIds[]', cb.value));

        const response = await fetch(this.bulkDeleteUrlValue, { method: 'POST', body });

        if (!response.ok) {
            window.alert('La suppression a échoué.');
            return;
        }

        checked.forEach((cb) => cb.closest('[data-gallery-selection-target="thumb"]').remove());
        this.update();
    }
}
