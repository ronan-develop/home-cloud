/**
 * HCAlert — Composant d'alerte/message réutilisable
 *
 * Utilisation via ModalFactory:
 *   const instance = await openModal('hc-alert', {
 *     title: 'Succès',
 *     data: { message: 'Fichier uploadé !', type: 'success', autoDismiss: 3000 }
 *   });
 *   await instance.asPromise(); // résout quand OK ou autoDismiss
 *
 * Types disponibles: success | error | warning | info
 */
class HCAlert extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._timer = null;
    }

    connectedCallback() {
        this._render();
    }

    setData(data = {}) {
        this._message = data.message || '';
        this._type = data.type || 'info';
        this._autoDismiss = data.autoDismiss || null;
        this._okText = data.okText || 'OK';
        this._render();
        if (this._autoDismiss) {
            this._timer = setTimeout(() => {
                this.dispatchEvent(new CustomEvent('submit', { detail: true }));
            }, this._autoDismiss);
        }
    }

    disconnectedCallback() {
        if (this._timer) clearTimeout(this._timer);
    }

    _render() {
        const iconMap = {
            success: '<polyline points="20 6 9 17 4 12"/>',
            error: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        };
        const colorMap = {
            success: { bg: 'rgba(16,185,129,0.12)', border: 'rgba(16,185,129,0.3)', text: '#6ee7b7' },
            error:   { bg: 'rgba(239,68,68,0.12)',  border: 'rgba(239,68,68,0.3)',  text: '#fca5a5' },
            warning: { bg: 'rgba(245,158,11,0.12)', border: 'rgba(245,158,11,0.3)', text: '#fcd34d' },
            info:    { bg: 'rgba(59,130,246,0.12)',  border: 'rgba(59,130,246,0.3)', text: '#93c5fd' },
        };
        const type = this._type || 'info';
        const colors = colorMap[type] || colorMap.info;
        const iconPaths = iconMap[type] || iconMap.info;

        this.shadowRoot.innerHTML = `
            <style>
                :host { display: block; font-family: inherit; }
                .alert-box {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.75rem;
                    padding: 0.75rem 1rem;
                    border-radius: 0.75rem;
                    background: ${colors.bg};
                    border: 1px solid ${colors.border};
                    margin-bottom: 1rem;
                }
                .alert-icon { flex-shrink: 0; line-height: 1; color: ${colors.text}; }
                .alert-icon svg { display: block; }
                .alert-message { color: ${colors.text}; font-size: 0.9rem; line-height: 1.5; }
                .alert-actions { display: flex; justify-content: flex-end; }
                button {
                    padding: 0.45rem 1rem;
                    border-radius: 0.55rem;
                    font-size: 0.875rem;
                    font-family: inherit;
                    cursor: pointer;
                    background: rgba(255,255,255,0.08);
                    border: 1px solid rgba(255,255,255,0.12);
                    color: rgba(255,255,255,0.85);
                    transition: all 0.15s ease;
                }
                button:hover { background: rgba(255,255,255,0.14); }
            </style>
            <div class="alert-box">
                <span class="alert-icon" data-type="${type}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${iconPaths}</svg>
                </span>
                <span class="alert-message">${this._escapeHtml(this._message || '')}</span>
            </div>
            <div class="alert-actions">
                <button class="alert-ok" type="button">${this._escapeHtml(this._okText || 'OK')}</button>
            </div>
        `;

        this.shadowRoot.querySelector('.alert-ok').addEventListener('click', () => {
            if (this._timer) clearTimeout(this._timer);
            this.dispatchEvent(new CustomEvent('submit', { detail: true }));
        });
    }

    _escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
}

customElements.define('hc-alert', HCAlert);
export { HCAlert };
