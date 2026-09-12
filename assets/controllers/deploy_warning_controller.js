import { Controller } from '@hotwired/stimulus';

/* Popup de préavis de déploiement (#422 étape 3/3) : poll /deploy-status
 * toutes les 30s, s'ouvre automatiquement si un préavis est en cours
 * (imminent: true) et affiche un compte à rebours mis à jour chaque
 * seconde depuis l'ETA reçue côté serveur. Se referme si le préavis
 * disparaît (déploiement terminé, échoué, ou reporté suite à une
 * reconnexion détectée par deploy-nightly.sh). */
export default class extends Controller {
    static targets = ['countdown'];

    connect() {
        this._poll();
        this._pollTimer = setInterval(() => this._poll(), 30000);
    }

    disconnect() {
        clearInterval(this._pollTimer);
        clearInterval(this._countdownTimer);
    }

    _poll() {
        fetch('/deploy-status')
            .then((response) => (response.ok ? response.json() : { imminent: false }))
            .then((data) => {
                if (data.imminent) {
                    this._startCountdown(data.etaSeconds);
                    this._open();
                } else {
                    this._close();
                }
            })
            .catch(() => {});
    }

    _startCountdown(etaSeconds) {
        clearInterval(this._countdownTimer);
        let remaining = etaSeconds;
        this._renderCountdown(remaining);

        this._countdownTimer = setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(this._countdownTimer);
                remaining = 0;
            }
            this._renderCountdown(remaining);
        }, 1000);
    }

    _renderCountdown(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        this.countdownTarget.textContent = `${minutes}:${String(secs).padStart(2, '0')}`;
    }

    _open() {
        this.element.classList.remove('hidden');
        this.element.style.display = 'flex';
    }

    _close() {
        clearInterval(this._countdownTimer);
        this.element.classList.add('hidden');
        this.element.style.display = 'none';
    }
}
