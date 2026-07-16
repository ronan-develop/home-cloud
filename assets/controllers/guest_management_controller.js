import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['createForm', 'createEmail', 'row'];

    async create(event) {
        event.preventDefault();
        const form = this.createFormTarget;
        const res = await this.postForm(form.action, new FormData(form));
        const data = await res.json();

        if (!res.ok) {
            window.showToast(data.error || 'Erreur lors de la création.', 'error');
            return;
        }

        window.showToast(data.message, 'success');
        this.createEmailTarget.value = '';
        window.location.reload();
    }

    async edit(event) {
        event.preventDefault();
        const form = event.target;
        const res = await this.postForm(form.action, new FormData(form));
        const data = await res.json();

        if (!res.ok) {
            window.showToast(data.error || 'Erreur lors de la mise à jour.', 'error');
            return;
        }

        window.showToast(data.message, 'success');
    }

    async delete(event) {
        event.preventDefault();
        const form = event.target;
        const displayName = form.dataset.guestDisplayName;

        if (!window.confirm(`Supprimer le compte invité « ${displayName} » ?`)) {
            return;
        }

        const res = await this.postForm(form.action, new FormData(form));
        const data = await res.json();

        if (!res.ok) {
            window.showToast(data.error || 'Erreur lors de la suppression.', 'error');
            return;
        }

        window.showToast(data.message, 'success');
        const row = form.closest('[data-guest-management-target="row"]');
        row?.remove();
    }

    postForm(url, formData) {
        return fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: formData,
        });
    }
}
