import { Controller } from '@hotwired/stimulus';

/* Réordonnancement des photos d'un album par glisser-déposer (HTML5 natif,
 * pas de dépendance externe). Soumet le nouvel ordre complet des mediaIds
 * à l'URL de la valeur "url" après chaque dépôt. */
export default class extends Controller {
    static targets = ['item'];
    static values = { url: String };

    dragStart(event) {
        this.dragged = event.currentTarget;
        event.dataTransfer.effectAllowed = 'move';
        this.dragged.classList.add('hc-media-thumb--dragging');
    }

    dragOver(event) {
        event.preventDefault();
        const target = event.currentTarget;
        if (!this.dragged || target === this.dragged) return;

        const items = this.itemTargets;
        const draggedIndex = items.indexOf(this.dragged);
        const targetIndex = items.indexOf(target);

        if (draggedIndex < targetIndex) {
            target.after(this.dragged);
        } else {
            target.before(this.dragged);
        }
    }

    drop(event) {
        event.preventDefault();
    }

    dragEnd() {
        if (!this.dragged) return;
        this.dragged.classList.remove('hc-media-thumb--dragging');
        this.dragged = null;
        this.submitNewOrder();
    }

    async submitNewOrder() {
        const mediaIds = this.itemTargets.map((el) => el.dataset.mediaId);
        const body = new URLSearchParams();
        mediaIds.forEach((id) => body.append('mediaIds[]', id));

        await fetch(this.urlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
    }
}
