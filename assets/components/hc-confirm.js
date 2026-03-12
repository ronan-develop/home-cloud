/**
 * HCConfirm — Dialog de confirmation réutilisable
 *
 * Utilisation via ModalFactory:
 *   const instance = await openModal('hc-confirm', {
 *     title: 'Supprimer ?',
 *     data: { message: 'Êtes-vous sûr ?', okText: 'Supprimer', cancelText: 'Annuler' }
 *   });
 *   const promise = instance.asPromise(); // true = OK, rejects = Cancel
 */
class HCConfirm extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
        this._render();
    }

    setData(data = {}) {
        this._message = data.message || '';
        this._okText = data.okText || 'Confirmer';
        this._cancelText = data.cancelText || 'Annuler';
        this._render();
    }

    _render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host { display: block; font-family: inherit; }
                .confirm-message {
                    color: rgba(255,255,255,0.85);
                    font-size: 0.95rem;
                    line-height: 1.5;
                    margin-bottom: 1.25rem;
                }
                .confirm-actions {
                    display: flex;
                    gap: 0.75rem;
                    justify-content: flex-end;
                }
                button {
                    padding: 0.5rem 1.1rem;
                    border-radius: 0.6rem;
                    font-size: 0.875rem;
                    font-family: inherit;
                    cursor: pointer;
                    transition: all 0.15s ease;
                    border: 1px solid transparent;
                }
                .confirm-cancel {
                    background: rgba(255,255,255,0.06);
                    border-color: rgba(255,255,255,0.12);
                    color: rgba(255,255,255,0.75);
                }
                .confirm-cancel:hover {
                    background: rgba(255,255,255,0.1);
                    color: rgba(255,255,255,0.95);
                }
                .confirm-ok {
                    background: rgba(239,68,68,0.2);
                    border-color: rgba(239,68,68,0.4);
                    color: #fca5a5;
                }
                .confirm-ok:hover {
                    background: rgba(239,68,68,0.3);
                }
            </style>
            <p class="confirm-message">${this._escapeHtml(this._message || '')}</p>
            <div class="confirm-actions">
                <button class="confirm-cancel" type="button">${this._escapeHtml(this._cancelText || 'Annuler')}</button>
                <button class="confirm-ok" type="button">${this._escapeHtml(this._okText || 'Confirmer')}</button>
            </div>
        `;

        this.shadowRoot.querySelector('.confirm-ok').addEventListener('click', () => {
            this.dispatchEvent(new CustomEvent('submit', { detail: true }));
        });

        this.shadowRoot.querySelector('.confirm-cancel').addEventListener('click', () => {
            this.dispatchEvent(new CustomEvent('cancel'));
        });
    }

    _escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
}

customElements.define('hc-confirm', HCConfirm);
export { HCConfirm };
