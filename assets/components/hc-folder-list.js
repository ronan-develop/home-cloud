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
                    background: rgba(255,255,255,0.06);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: rgba(255,255,255,0.8);
                    font-size: 0.85rem;
                    cursor: pointer;
                    width: 100%;
                    text-align: left;
                    transition: background 0.15s;
                }

                .folder-option:hover {
                    background: rgba(255,255,255,0.1);
                }

                .folder-option.active {
                    background: rgba(59,130,246,0.25);
                    border-color: rgba(59,130,246,0.4);
                    color: #fff;
                }

                .folder-icon {
                    font-size: 1rem;
                }

                .new-folder-input {
                    width: 100%;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.75rem;
                    background: rgba(255,255,255,0.05);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: rgba(255,255,255,0.8);
                    font-size: 0.85rem;
                    outline: none;
                    box-sizing: border-box;
                    transition: border-color 0.15s;
                }

                .new-folder-input:focus {
                    border-color: rgba(59,130,246,0.5);
                    background: rgba(255,255,255,0.08);
                }

                .new-folder-input::placeholder {
                    color: rgba(255,255,255,0.3);
                }

                .folder-list-empty {
                    padding: 0.75rem;
                    color: rgba(255,255,255,0.4);
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
                            <span class="folder-icon">${f.icon || '📁'}</span>
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
