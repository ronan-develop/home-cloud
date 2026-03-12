/**
 * HCModal — Webcomponent générique pour toutes les modales
 * 
 * Encapsule le design Liquid Glass + gestion open/close/ESC
 * Accepte des enfants via slot 'content'
 * 
 * Utilisation:
 *   <hc-modal title="Mon titre" size="medium">
 *     <div slot="content">Contenu personnalisé</div>
 *     <div slot="actions"><button>OK</button></div>
 *   </hc-modal>
 * 
 * API:
 *   modal.open()     — afficher
 *   modal.close()    — masquer
 *   modal.setTitle(text) — changer titre
 */
class HCModal extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._isOpen = false;
    }

    connectedCallback() {
        this.render();
        this._setupEventListeners();
    }

    /**
     * Rendu initial du template de la modal
     */
    render() {
        const title = this.getAttribute('title') || '';
        const size = this.getAttribute('size') || 'medium';
        const closeable = this.getAttribute('closeable') !== 'false';

        // Map size to max-width
        const sizeMap = {
            'small': '320px',
            'medium': '480px',
            'large': '720px',
        };
        const maxWidth = sizeMap[size] || sizeMap['medium'];

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    --hc-modal-max-width: ${maxWidth};
                }

                .hc-modal-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 9999;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    background: rgba(0, 0, 0, 0.45);
                    backdrop-filter: blur(4px);
                    animation: hc-fadeIn 0.2s ease;
                }

                .hc-modal-overlay.open {
                    display: flex;
                }

                @keyframes hc-fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes hc-slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                /* Liquid Glass Card */
                .hc-modal-card {
                    position: relative;
                    width: 100%;
                    max-width: var(--hc-modal-max-width);
                    margin: 1rem;
                    border-radius: 1.6rem;
                    overflow: hidden;
                    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.22);
                    animation: hc-slideUp 0.3s ease;
                }

                /* LG Layer 1: Blurred backdrop */
                .hc-modal-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: rgba(255, 255, 255, 0.08);
                    backdrop-filter: blur(24px) saturate(200%);
                    pointer-events: none;
                    border-radius: 1.6rem;
                }

                /* LG Layer 2: Tint overlay */
                .hc-modal-card::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: rgba(255, 255, 255, 0.06);
                    pointer-events: none;
                    border-radius: 1.6rem;
                }

                /* Modal Content */
                .hc-modal-header {
                    position: relative;
                    z-index: 10;
                    padding: 1.5rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                }

                .hc-modal-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: rgba(255, 255, 255, 0.95);
                    margin: 0;
                }

                .hc-modal-close-btn {
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.5);
                    cursor: pointer;
                    font-size: 1.5rem;
                    padding: 0;
                    width: 2rem;
                    height: 2rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.5rem;
                    transition: all 0.2s ease;
                }

                .hc-modal-close-btn:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: rgba(255, 255, 255, 0.8);
                }

                .hc-modal-content {
                    position: relative;
                    z-index: 10;
                    padding: 1.5rem;
                }

                .hc-modal-actions {
                    position: relative;
                    z-index: 10;
                    padding: 1rem 1.5rem;
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                    display: flex;
                    gap: 0.75rem;
                    justify-content: flex-end;
                }

                /* Responsive */
                @media (max-width: 640px) {
                    .hc-modal-card {
                        max-width: 95%;
                        margin: 0.5rem;
                    }

                    .hc-modal-header {
                        padding: 1rem;
                    }

                    .hc-modal-content {
                        padding: 1rem;
                    }

                    .hc-modal-actions {
                        padding: 1rem;
                        flex-direction: column;
                    }
                }
            </style>

            <div class="hc-modal-overlay">
                <div class="hc-modal-card">
                    ${title ? `
                        <div class="hc-modal-header">
                            <h2 class="hc-modal-title">${this._escapeHtml(title)}</h2>
                            ${closeable ? '<button class="hc-modal-close-btn" aria-label="Fermer">✕</button>' : ''}
                        </div>
                    ` : ''}
                    <div class="hc-modal-content">
                        <slot name="content"></slot>
                    </div>
                    <div class="hc-modal-actions" style="display: none;">
                        <slot name="actions"></slot>
                    </div>
                </div>
            </div>
        `;

        // Masquer la section actions si vide
        this._updateActionsVisibility();
    }

    /**
     * Setup event listeners for close button, ESC key, and backdrop
     */
    _setupEventListeners() {
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        const closeBtn = this.shadowRoot.querySelector('.hc-modal-close-btn');

        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // ESC key
        this._escapeHandler = (e) => {
            if (e.key === 'Escape' && this._isOpen) {
                this.close();
            }
        };
        document.addEventListener('keydown', this._escapeHandler);

        // Backdrop click (fermer si clique sur overlay, pas sur card)
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.close();
            }
        });
    }

    /**
     * Montrer la modal
     */
    open() {
        if (this._isOpen) return;
        this._isOpen = true;
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        this.dispatchEvent(new CustomEvent('modal:open'));
    }

    /**
     * Masquer la modal
     */
    close() {
        if (!this._isOpen) return;
        this._isOpen = false;
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        overlay.classList.remove('open');
        document.body.style.overflow = 'auto';
        this.dispatchEvent(new CustomEvent('modal:close'));
    }

    /**
     * Changer le titre
     */
    setTitle(text) {
        const titleEl = this.shadowRoot.querySelector('.hc-modal-title');
        if (titleEl) {
            titleEl.textContent = text;
        }
    }

    /**
     * Afficher/masquer la section actions dynamiquement
     */
    _updateActionsVisibility() {
        const actionsSlot = this.shadowRoot.querySelector('slot[name="actions"]');
        const actionsContainer = this.shadowRoot.querySelector('.hc-modal-actions');
        
        if (actionsSlot && actionsContainer) {
            const assignedNodes = actionsSlot.assignedNodes();
            actionsContainer.style.display = assignedNodes.length > 0 ? 'flex' : 'none';
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    _escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    disconnectedCallback() {
        document.removeEventListener('keydown', this._escapeHandler);
    }
}

customElements.define('hc-modal', HCModal);
export { HCModal };
