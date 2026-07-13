import { Controller } from '@hotwired/stimulus';

/* Sélection multiple de médias dans la galerie, active uniquement en contexte
 * d'ajout à un album (?album=...). Affiche la barre d'action flottante dès
 * qu'au moins un média est coché. */
export default class extends Controller {
    static targets = ['thumb', 'checkbox', 'bar', 'count'];

    update() {
        const checked = this.checkboxTargets.filter((cb) => cb.checked);

        this.checkboxTargets.forEach((cb) => {
            cb.closest('[data-gallery-selection-target="thumb"]')
                .classList.toggle('hc-media-thumb--selected', cb.checked);
        });

        this.countTarget.textContent = `${checked.length} sélectionné${checked.length > 1 ? 's' : ''}`;
        this.barTarget.classList.toggle('hidden', checked.length === 0);
    }
}
