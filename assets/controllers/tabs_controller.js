import { Controller } from '@hotwired/stimulus';

/* Bascule entre panneaux via des boutons d'onglets — chaque bouton porte
 * data-tab="xxx", chaque panneau data-tab-panel="xxx". Réutilisable par
 * toute UI à onglets simple (ShareModal, futures UI similaires). */
export default class extends Controller {
    static targets = ['button', 'panel'];
    static classes = ['active'];

    select(event) {
        const target = event.currentTarget.dataset.tab;

        this.buttonTargets.forEach((btn) => {
            btn.classList.toggle(this.activeClass, btn.dataset.tab === target);
        });
        this.panelTargets.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.tabPanel !== target);
        });
    }
}
