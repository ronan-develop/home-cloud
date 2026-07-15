import { Controller } from '@hotwired/stimulus';

/* Expose window.HC.getToken()/userId — pont d'authentification JWT utilisé
 * par api.js et les modales qui font des appels à l'API depuis le navigateur
 * (upload, création de dossier, réglages). Le token est mis en cache 14 min
 * (durée de vie du JWT côté serveur), rafraîchi via /web/token à l'expiration. */
export default class extends Controller {
    static values = { userId: String, tokenUrl: String };

    connect() {
        let token = null;
        let tokenExpiresAt = 0;

        window.HC = {
            userId: this.userIdValue,
            getToken: async () => {
                if (token && Date.now() < tokenExpiresAt) return token;
                const res = await fetch(this.tokenUrlValue);
                const data = await res.json();
                token = data.token;
                tokenExpiresAt = Date.now() + 14 * 60 * 1000;
                return token;
            },
        };
    }
}
