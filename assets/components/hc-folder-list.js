/**
 * HCFolderList — WebComponent sélecteur de dossiers
 *
 * Usage:
 *   const el = document.createElement('hc-folder-list');
 *   el.setFolders([{ id, name, icon }]);
 *   el.setSelected('folder-id');
 *   el.getSelected() → { id, name, icon, isNew, newName }
 *   el.addEventListener('folder-list:change', e => e.detail)
 */
class HCFolderList extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._folders = [];
        this._selectedId = null;
        this._newName = '';
    }

    connectedCallback() {
        this._render();
    }

    setFolders(folders) {
        this._folders = folders || [];
        this._selectedId = folders.length > 0 ? folders[0].id : null;
        this._newName = '';
        this._render();
    }

    setSelected(id) {
        this._selectedId = id;
        this._newName = '';
        this._updateActive();
    }

    getSelected() {
        if (this._newName) {
            return { id: null, isNew: true, newName: this._newName };
        }
        const folder = this._folders.find(f => f.id === this._selectedId);
        if (folder) return { ...folder, isNew: false };
        // Dossier connu (ex: sous-dossier non listé par l'API) — on retourne l'ID tel quel
        if (this._selectedId) return { id: this._selectedId, isNew: false };
        return { id: null, isNew: false };
    }

    _render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host { display: block; }

                .folder-list {
                    display: flex;
                    flex-direction: column;
                    gap: 0.4rem;
                    margin-bottom: 0.75rem;
                }

                .folder-option {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.75rem;
                    background: var(--hc-surface-2);
                    border: 1px solid var(--hc-border);
                    color: var(--hc-text-2);
                    font-size: 0.85rem;
                    cursor: pointer;
                    width: 100%;
                    text-align: left;
                    transition: background 0.15s;
                }

                .folder-option:hover {
                    background: var(--hc-input);
                }

                .folder-option.active {
                    background: var(--hc-accent-soft);
                    border-color: var(--hc-accent);
                    color: var(--hc-text);
                }

                .folder-icon {
                    font-size: 1rem;
                }

                .new-folder-input {
                    width: 100%;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.75rem;
                    background: var(--hc-input);
                    border: 1px solid var(--hc-border);
                    color: var(--hc-text);
                    font-size: 0.85rem;
                    outline: none;
                    box-sizing: border-box;
                    transition: border-color 0.15s;
                }

                .new-folder-input:focus {
                    border-color: var(--hc-accent);
                    background: var(--hc-surface-2);
                }

                .new-folder-input::placeholder {
                    color: var(--hc-text-3);
                }

                .folder-list-empty {
                    padding: 0.75rem;
                    color: var(--hc-text-3);
                    font-size: 0.85rem;
                    font-style: italic;
                    text-align: center;
                }
            </style>

            <div class="folder-list" role="listbox" aria-label="Dossiers disponibles">
                ${this._folders.length === 0
                    ? '<div class="folder-list-empty">Aucun dossier disponible</div>'
                    : this._folders.map(f => `
                        <button
                            type="button"
                            class="folder-option${f.id === this._selectedId ? ' active' : ''}"
                            data-folder-id="${f.id}"
                            tabindex="0"
                            role="option"
                            aria-selected="${f.id === this._selectedId}"
                        >
                            <span class="folder-icon">${f.icon || '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>'}</span>
                            <span>${f.name}</span>
                        </button>
                    `).join('')}
            </div>
            <input
                type="text"
                class="new-folder-input"
                placeholder="Ou créer un dossier…"
                aria-label="Créer un nouveau dossier"
                value="${this._newName}"
            >
        `;

        this._attachListeners();
    }

    _attachListeners() {
        this.shadowRoot.querySelectorAll('.folder-option').forEach(btn => {
            btn.addEventListener('click', () => this._selectFolder(btn));
            btn.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._selectFolder(btn);
                }
            });
        });

        const input = this.shadowRoot.querySelector('.new-folder-input');
        input.addEventListener('input', () => {
            this._newName = input.value.trim();
            if (this._newName) {
                this._selectedId = null;
                this._updateActive();
            }
        });
    }

    _selectFolder(btn) {
        this._selectedId = btn.getAttribute('data-folder-id');
        this._newName = '';
        const input = this.shadowRoot.querySelector('.new-folder-input');
        if (input) input.value = '';
        this._updateActive();

        const folder = this._folders.find(f => f.id === this._selectedId);
        this.dispatchEvent(new CustomEvent('folder-list:change', {
            bubbles: true,
            detail: { ...folder, isNew: false },
        }));
    }

    _updateActive() {
        this.shadowRoot.querySelectorAll('.folder-option').forEach(btn => {
            const isActive = btn.getAttribute('data-folder-id') === this._selectedId;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', String(isActive));
        });
    }
}

customElements.define('hc-folder-list', HCFolderList);
export { HCFolderList };
