import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'password', 'message'];
    static values = { token: String, loginUrl: String };

    async submit(event) {
        event.preventDefault();
        this.messageTarget.innerHTML = '';

        const newPassword = this.passwordTarget.value;
        const res = await fetch('/api/reset-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: this.tokenValue, password: newPassword }),
        });
        const data = await res.json();

        if (res.ok) {
            this.messageTarget.innerHTML = `<div class="flash-success">${data.message || 'Mot de passe réinitialisé.'}</div>`;
            this.formTarget.reset();
            const redirectUrl = `${this.loginUrlValue}?email=${encodeURIComponent(data.email)}`;
            window.setTimeout(() => window.location.assign(redirectUrl), 1200);
        } else {
            this.messageTarget.innerHTML = `<div class="flash-error">${data.error || 'Erreur lors de la réinitialisation.'}</div>`;
        }
    }
}
