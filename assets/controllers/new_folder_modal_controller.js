import { Controller } from '@hotwired/stimulus';

/* Modale de création de dossier : ouverture (écoute new-menu:open-folder-modal
 * émis par NewMenu), sélection du type de média, soumission POST /api/v1/folders
 * via le token bridge (window.HC.getToken), fermeture (bouton, overlay, Échap). */
export default class extends Controller {
    static targets = ['nameInput', 'parentSelect', 'mediaTypeLabel', 'mediaTypeRadio', 'submitButton', 'submitLabel'];
    static values = { createUrl: String, ownerId: String };

    connect() {
        this._onOpenRequest = () => this.open();
        document.addEventListener('new-menu:open-folder-modal', this._onOpenRequest);
    }

    disconnect() {
        document.removeEventListener('new-menu:open-folder-modal', this._onOpenRequest);
    }

    open() {
        this.nameInputTarget.value = '';
        if (this.hasParentSelectTarget) this.parentSelectTarget.value = '';
        this.mediaTypeRadioTargets.forEach((radio) => {
            radio.checked = radio.value === 'general';
        });
        this.updateMediaTypeStyles();
        this.element.classList.remove('hidden');
        setTimeout(() => this.nameInputTarget.focus(), 50);
    }

    close() {
        this.element.classList.add('hidden');
    }

    closeOnOverlayClick(event) {
        if (event.target === this.element) this.close();
    }

    handleNameKeydown(event) {
        if (event.key === 'Enter') this.submit();
        if (event.key === 'Escape') this.close();
    }

    selectMediaType() {
        this.updateMediaTypeStyles();
    }

    updateMediaTypeStyles() {
        this.mediaTypeLabelTargets.forEach((label) => {
            const radio = label.querySelector('input[type="radio"]');
            label.classList.toggle('hc-new-folder-media-type--active', !!radio?.checked);
        });
    }

    async submit() {
        const name = this.nameInputTarget.value.trim();
        if (!name) {
            this.nameInputTarget.focus();
            return;
        }

        const checked = this.mediaTypeRadioTargets.find((r) => r.checked);
        const mediaType = checked ? checked.value : 'general';
        const parentId = this.hasParentSelectTarget ? (this.parentSelectTarget.value || null) : null;

        this.submitButtonTarget.disabled = true;
        this.submitLabelTarget.textContent = '…';

        const token = await window.HC.getToken();
        const res = await fetch(this.createUrlValue, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name,
                ownerId: this.ownerIdValue,
                mediaType,
                parentId,
            }),
        });

        if (res.ok) {
            this.close();
            window.location.reload();
        } else {
            this.submitButtonTarget.disabled = false;
            this.submitLabelTarget.textContent = 'Créer';
            window.alert('Erreur lors de la création du dossier.');
        }
    }
}
