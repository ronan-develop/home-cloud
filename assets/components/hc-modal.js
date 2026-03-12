/**
 * HCModal v2 — WebComponent modal générique et polymorphe
 *
 * Buts:
 * - Conteneur d'interface (overlay, animations, accessibility)
 * - Support d'injection dynamique de composants (setContent)
 * - Transmission de données via setData / props
 * - API évènementielle et lifecycle utilisable par ModalFactory
 *
 * Utilisation (slot / light DOM compatible):
 *  <hc-modal title="Mon titre" size="medium">
 *    <hc-upload-form slot="content"></hc-upload-form>
 *  </hc-modal>
 *
 * Utilisation (factory async):
 *  const modal = await openModal(HCUploadForm, { title: 'Upload' });
 *  modal.on('submit', data => ...);
 */

let _modalZ = 9999; // base z-index for stacking

class HCModal extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._isOpen = false;
        this._previousActive = null;
        this._escapeHandler = null;
        this._focusHandler = null;
        this._resolve = null; // for promise-based flows
        this._reject = null;
        this._contentRef = null;
    }

    connectedCallback() {
        this.render();
        this._setupEventListeners();
    }

    render() {
        const title = this.getAttribute('title') || '';
        const size = this.getAttribute('size') || 'medium';
        const closeable = this.getAttribute('closeable') !== 'false';

        const sizeMap = { small: '320px', medium: '480px', large: '720px', fullscreen: '100%' };
        const maxWidth = sizeMap[size] || sizeMap.medium;

        // Unique ids for aria
        const titleId = `hc-modal-title-${Math.random().toString(36).slice(2,8)}`;

        this.shadowRoot.innerHTML = `
            <style>
                :host { position: fixed; inset: 0; z-index: ${_modalZ}; display: block; }
                .hc-modal-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.45); }
                .hc-modal-overlay.open { display: flex; }
                .hc-modal-card { position: relative; width: 100%; max-width: ${maxWidth}; margin: 1rem; border-radius: 1.6rem; overflow: hidden; box-shadow: 0 8px 32px rgba(31,38,135,0.22); background: transparent; }
                .hc-modal-card::before { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,0.06); backdrop-filter: blur(18px) saturate(180%); pointer-events: none; border-radius: 1.6rem; }
                .hc-modal-header { position: relative; z-index: 10; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; }
                .hc-modal-title { margin:0; font-size:1.1rem; font-weight:600; color:inherit; }
                .hc-modal-close-btn { background:none; border:none; cursor:pointer; font-size:1.25rem; padding:0.25rem; }
                .hc-modal-content { position: relative; z-index:10; padding: 1rem 1.25rem; }
                .hc-modal-footer { position:relative; z-index:10; padding:0.75rem 1.25rem; border-top: 1px solid rgba(255,255,255,0.06); display:flex; gap:0.5rem; justify-content:flex-end; }
                @media (max-width:640px) { .hc-modal-card { max-width:95%; margin:0.5rem; } .hc-modal-content{padding:0.8rem} }
            </style>
            <div class="hc-modal-overlay" tabindex="-1" part="overlay">
                <div class="hc-modal-card" role="dialog" aria-modal="true" aria-labelledby="${title ? titleId : ''}" part="card">
                    ${title ? `
                        <div class="hc-modal-header" part="header">
                            <h2 id="${titleId}" class="hc-modal-title">${this._escapeHtml(title)}</h2>
                            ${closeable ? '<button class="hc-modal-close-btn" aria-label="Fermer">✕</button>' : ''}
                        </div>
                    ` : ''}

                    <div class="hc-modal-content" part="content">
                        <slot name="content"></slot>
                    </div>

                    <div class="hc-modal-footer" part="footer">
                        <slot name="actions"></slot>
                    </div>
                </div>
            </div>
        `;

        // Update actions visibility on slot change
        const actionsSlot = this.shadowRoot.querySelector('slot[name="actions"]');
        if (actionsSlot) {
            actionsSlot.addEventListener('slotchange', () => this._updateActionsVisibility());
            this._updateActionsVisibility();
        }
    }

    _setupEventListeners() {
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        const closeBtn = this.shadowRoot.querySelector('.hc-modal-close-btn');

        if (closeBtn) closeBtn.addEventListener('click', () => this._handleCancel());

        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && this.getAttribute('backdrop') !== 'static') this._handleCancel();
            });
        }

        // ESC key
        this._escapeHandler = (e) => {
            if (e.key === 'Escape' && this._isOpen && this.getAttribute('backdrop') !== 'static') this._handleCancel();
        };
        document.addEventListener('keydown', this._escapeHandler);

        // Focus trap
        this._focusHandler = (e) => this._trapFocus(e);
    }

    _updateActionsVisibility() {
        const actionsSlot = this.shadowRoot.querySelector('slot[name="actions"]');
        const footer = this.shadowRoot.querySelector('.hc-modal-footer');
        if (!actionsSlot || !footer) return;
        const nodes = actionsSlot.assignedNodes({ flatten: true }).filter(n => !(n.nodeType === Node.TEXT_NODE && n.textContent.trim() === ''));
        footer.style.display = nodes.length > 0 ? 'flex' : 'none';
    }

    _handleCancel() {
        this.dispatchEvent(new CustomEvent('modal:cancel'));
        this.close();
        if (this._reject) this._reject(new Error('cancel'));
    }

    _handleSubmit(detail) {
        this.dispatchEvent(new CustomEvent('modal:submit', { detail }));
        this.close();
        if (this._resolve) this._resolve(detail);
    }

    open() {
        if (this._isOpen) return this;
        this._isOpen = true;
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        overlay.classList.add('open');

        // z-index stacking
        this.style.zIndex = ++_modalZ;

        // prevent body scroll
        document.body.style.overflow = 'hidden';

        // focus management
        this._previousActive = document.activeElement;
        this._focusFirstDescendant();
        document.addEventListener('focus', this._focusHandler, true);

        this.dispatchEvent(new CustomEvent('modal:open'));
        return this;
    }

    close() {
        if (!this._isOpen) return this;
        this._isOpen = false;
        const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        document.removeEventListener('focus', this._focusHandler, true);
        if (this._previousActive && typeof this._previousActive.focus === 'function') this._previousActive.focus();
        this.dispatchEvent(new CustomEvent('modal:close'));
    }

    // Promise-based convenience: resolves on submit, rejects on cancel
    asPromise() {
        return new Promise((resolve, reject) => { this._resolve = resolve; this._reject = reject; });
    }

    // Set a light-dom component as content; it must be an Element
    setContent(component) {
        if (!(component instanceof Element)) throw new TypeError('component must be a DOM Element');
        component.setAttribute('slot', 'content');
        this.appendChild(component);
        this._contentRef = component;
        return this;
    }

    // Convenience: setData will try to set property or dispatch event
    setData(data) {
        if (this._contentRef) {
            try {
                if (typeof this._contentRef.setData === 'function') {
                    this._contentRef.setData(data);
                } else {
                    this._contentRef.data = data;
                }
            } catch (e) {
                // fallback: dispatch event
                this._contentRef.dispatchEvent(new CustomEvent('modal:set-data', { detail: data }));
            }
        }
        return this;
    }

    // Utility: escape html
    _escapeHtml(text) {
        const map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Focus helpers
    _focusFirstDescendant() {
        const focusable = this._getFocusableElements();
        if (focusable.length) {
            focusable[0].focus();
        } else {
            const overlay = this.shadowRoot.querySelector('.hc-modal-overlay');
            overlay.focus();
        }
    }

    _getFocusableElements() {
        const el = this.shadowRoot.querySelector('.hc-modal-card');
        if (!el) return [];
        const selectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex], [contenteditable]';
        return Array.from(el.querySelectorAll(selectors)).filter(e => e.tabIndex !== -1 && !e.hasAttribute('disabled'));
    }

    _trapFocus(e) {
        if (!this._isOpen) return;
        const focusable = this._getFocusableElements();
        if (!focusable.length) { e.preventDefault(); return; }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.target === last && e.shiftKey === false && e.relatedTarget === null) return; // normal
        // ensure focus stays in modal
        if (!this.contains(document.activeElement)) {
            first.focus();
        }
    }

    disconnectedCallback() {
        document.removeEventListener('keydown', this._escapeHandler);
        document.removeEventListener('focus', this._focusHandler, true);
    }
}

customElements.define('hc-modal', HCModal);
export { HCModal };
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
