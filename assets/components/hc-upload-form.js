/**
 * HCUploadForm — Composant pour orchestrer l'upload de fichiers
 * 
 * Gère:
 * - Affichage des dossiers existants
 * - Création d'un nouveau dossier
 * - Liste des fichiers sélectionnés
 * - Progress bars pour chaque upload
 * 
 * Utilisation:
 *   <hc-upload-form id="upload-form"></hc-upload-form>
 *   form.initialize(files, folders)
 *   form.addEventListener('upload-form:submit', (e) => { ... })
 */
class HCUploadForm extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.files = [];
        this.folders = [];
        this.currentFolderId = null;
        this.folderOptions = [];
    }

    connectedCallback() {
        // Template de base (sera rempli par initialize())
        this._renderTemplate();
    }

    /**
     * Initialiser le formulaire avec les fichiers et dossiers
     */
    initialize(files, folders, options = {}) {
        this.files = files || [];
        this.folders = folders || [];
        this.currentFolderId = options.folderId || null;
        this.folderOptions = [];
        this._render();
        this._attachEventListeners();
    }

    /**
     * Rendu du template
     */
    _renderTemplate() {
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                }

                .upload-form {
                    display: flex;
                    flex-direction: column;
                    gap: 1.25rem;
                }

                /* Destination section */
                .destination-section {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }

                .section-label {
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: rgba(255, 255, 255, 0.5);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .folder-list {
                    display: flex;
                    flex-direction: column;
                    gap: 0.35rem;
                }

                .folder-option {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.75rem;
                    background: rgba(255, 255, 255, 0.04);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    color: rgba(255, 255, 255, 0.75);
                    font-size: 0.85rem;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    font-family: inherit;
                    width: 100%;
                    text-align: left;
                }

                .folder-option:hover {
                    background: rgba(255, 255, 255, 0.08);
                    border-color: rgba(255, 255, 255, 0.15);
                }

                .folder-option.active {
                    background: rgba(59, 130, 246, 0.15);
                    border-color: rgba(59, 130, 246, 0.3);
                    color: rgba(255, 255, 255, 0.95);
                }

                .folder-icon {
                    font-size: 1.1rem;
                    flex-shrink: 0;
                }

                .new-folder-input {
                    width: 100%;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.75rem;
                    background: rgba(255, 255, 255, 0.04);
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 0.85rem;
                    font-family: inherit;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }

                .new-folder-input::placeholder {
                    color: rgba(255, 255, 255, 0.35);
                }

                .new-folder-input:focus {
                    outline: none;
                    background: rgba(255, 255, 255, 0.06);
                    border-color: rgba(59, 130, 246, 0.4);
                    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
                }

                /* File list */
                .file-list {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    max-height: 280px;
                    overflow-y: auto;
                    padding-right: 0.25rem;
                }

                .file-list::-webkit-scrollbar {
                    width: 4px;
                }

                .file-list::-webkit-scrollbar-track {
                    background: rgba(255, 255, 255, 0.04);
                    border-radius: 4px;
                }

                .file-list::-webkit-scrollbar-thumb {
                    background: rgba(255, 255, 255, 0.12);
                    border-radius: 4px;
                }

                .file-list::-webkit-scrollbar-thumb:hover {
                    background: rgba(255, 255, 255, 0.18);
                }

                .file-item {
                    display: flex;
                    flex-direction: column;
                    gap: 0.35rem;
                    padding: 0.6rem 0.75rem;
                    border-radius: 0.85rem;
                    background: rgba(255, 255, 255, 0.06);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    transition: all 0.2s ease;
                }

                .file-item:hover {
                    background: rgba(255, 255, 255, 0.08);
                    border-color: rgba(255, 255, 255, 0.12);
                }

                .file-item__header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.5rem;
                }

                .file-item__icon {
                    font-size: 1rem;
                    flex-shrink: 0;
                }

                .file-item__name {
                    font-size: 0.8rem;
                    font-weight: 500;
                    color: rgba(255, 255, 255, 0.9);
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    flex: 1;
                }

                .file-item__meta {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .file-item__size {
                    font-size: 0.72rem;
                    color: rgba(255, 255, 255, 0.45);
                }

                .file-item__status {
                    font-size: 0.72rem;
                    font-weight: 600;
                    min-width: 3rem;
                    text-align: right;
                }

                .file-item__status--pending {
                    color: rgba(255, 255, 255, 0.4);
                }

                .file-item__status--uploading {
                    color: #60a5fa;
                }

                .file-item__status--done {
                    color: #34d399;
                }

                .file-item__status--error {
                    color: #f87171;
                }

                .file-item__progress {
                    height: 3px;
                    background: rgba(255, 255, 255, 0.08);
                    border-radius: 2px;
                    overflow: hidden;
                }

                .file-item__bar {
                    height: 100%;
                    background: linear-gradient(90deg, #3b82f6, #60a5fa);
                    border-radius: 2px;
                    transition: width 0.3s ease;
                    box-shadow: 0 0 6px rgba(59, 130, 246, 0.4);
                }

                .file-item--done .file-item__bar {
                    background: linear-gradient(90deg, #10b981, #34d399);
                    box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
                }

                .file-item--error .file-item__bar {
                    background: linear-gradient(90deg, #ef4444, #f87171);
                    box-shadow: 0 0 6px rgba(239, 68, 68, 0.4);
                }

                /* Responsive */
                @media (max-width: 640px) {
                    .file-list {
                        max-height: 200px;
                    }
                }
            </style>

            <div class="upload-form">
                <div class="destination-section">
                    <label class="section-label">Destination</label>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <div style="flex:1">
                            <div class="folder-list" id="folder-list" role="listbox" aria-label="Folder list"></div>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:0.35rem; width:220px;">
                            <input 
                                type="text" 
                                class="new-folder-input" 
                                id="new-folder-input"
                                placeholder="Ou créer un dossier…"
                                aria-label="Créer un dossier"
                            >
                            <button id="create-folder-btn" type="button" style="padding:0.45rem 0.6rem; border-radius:0.6rem; background:rgba(59,130,246,0.15); border:1px solid rgba(59,130,246,0.25); color:inherit; cursor:pointer; font-size:0.85rem;">Créer</button>
                        </div>
                    </div>
                </div>

                <div class="file-list" id="file-list"></div>
            </div>
        `;
    }

    /**
     * Afficher les dossiers et fichiers
     */
    _render() {
        this._renderFolders();
        this._renderFiles();
    }

    /**
     * Rendre les boutons de dossiers
     */
    _renderFolders() {
        const folderList = this.shadowRoot.getElementById('folder-list');
        folderList.innerHTML = '';
        this.folderOptions = [];

        if (this.folders.length === 0) {
            return;
        }

        this.folders.forEach((folder, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'folder-option';
            btn.setAttribute('data-folder-id', folder.id);

            const icon = document.createElement('span');
            icon.className = 'folder-icon';
            icon.textContent = folder.icon || '📁';

            const name = document.createElement('span');
            name.textContent = folder.name;

            btn.appendChild(icon);
            btn.appendChild(name);

            // Marquer le premier dossier ou le dossier courant comme actif
            if (folder.id === this.currentFolderId || (idx === 0 && !this.currentFolderId)) {
                btn.classList.add('active');
            }

            btn.addEventListener('click', () => {
                this._selectFolderBtn(btn);
            });

            // keyboard support
            btn.setAttribute('tabindex', '0');
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._selectFolderBtn(btn);
                }
            });

            folderList.appendChild(btn);
            this.folderOptions.push(btn);
        });
    }

    /**
     * Rendre la liste des fichiers
     */
    _renderFiles() {
        const fileList = this.shadowRoot.getElementById('file-list');
        fileList.innerHTML = '';

        this.files.forEach(file => {
            const item = document.createElement('div');
            item.className = 'file-item';
            item.setAttribute('data-file-name', file.name);

            const header = document.createElement('div');
            header.className = 'file-item__header';

            const icon = document.createElement('span');
            icon.className = 'file-item__icon';
            icon.textContent = '📎';

            const name = document.createElement('span');
            name.className = 'file-item__name';
            name.textContent = file.name;

            header.appendChild(icon);
            header.appendChild(name);

            const meta = document.createElement('div');
            meta.className = 'file-item__meta';

            const size = document.createElement('span');
            size.className = 'file-item__size';
            size.textContent = this._formatFileSize(file.size);

            const status = document.createElement('span');
            status.className = 'file-item__status file-item__status--pending';
            status.textContent = 'En attente';

            meta.appendChild(size);
            meta.appendChild(status);

            header.appendChild(meta);

            const progress = document.createElement('div');
            progress.className = 'file-item__progress';

            const bar = document.createElement('div');
            bar.className = 'file-item__bar';
            bar.style.width = '0%';

            progress.appendChild(bar);

            item.appendChild(header);
            item.appendChild(progress);

            fileList.appendChild(item);
        });
    }

    /**
     * Attacher les event listeners
     */
    _attachEventListeners() {
        const newFolderInput = this.shadowRoot.getElementById('new-folder-input');
        const createBtn = this.shadowRoot.getElementById('create-folder-btn');

        newFolderInput.addEventListener('input', (e) => {
            if (e.target.value.trim()) {
                this.folderOptions.forEach(opt => opt.classList.remove('active'));
            }
        });

        // Enter creates folder
        newFolderInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const name = newFolderInput.value.trim();
                if (name) this._createFolder(name);
            }
        });

        if (createBtn) {
            createBtn.addEventListener('click', () => {
                const name = newFolderInput.value.trim();
                if (name) this._createFolder(name);
            });
        }
    }

    /**
     * Obtenir le dossier de destination choisi
     */
    getDestinationFolder() {
        const activeBtn = this.shadowRoot.querySelector('.folder-option.active');
        if (activeBtn) {
            return {
                id: activeBtn.getAttribute('data-folder-id'),
                isNew: false,
            };
        }

        const newFolderInput = this.shadowRoot.getElementById('new-folder-input');
        if (newFolderInput && newFolderInput.value.trim()) {
            return {
                name: newFolderInput.value.trim(),
                isNew: true,
            };
        }

        return null;
    }

    /**
     * Mettre à jour l'état d'un fichier
     */
    updateFileProgress(fileName, state, progress, error) {
        const item = this.shadowRoot.querySelector(`[data-file-name="${fileName}"]`);
        if (!item) return;

        const stateClass = state.toLowerCase();
        item.className = `file-item file-item--${stateClass}`;

        const statusEl = item.querySelector('.file-item__status');
        const statusMap = {
            uploading: '⬆️ En cours',
            completed: '✅ Terminé',
            done: '✅ Terminé',
            error: '❌ Erreur',
            cancelled: '⏹️ Annulé',
            pending: '⏳ En attente',
        };
        statusEl.textContent = statusMap[stateClass] || state;
        statusEl.className = `file-item__status file-item__status--${stateClass}`;

        const barEl = item.querySelector('.file-item__bar');
        barEl.style.width = `${progress || 0}%`;
    }

    /**
     * Formater la taille d'un fichier
     */
    _formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 10) / 10 + ' ' + sizes[i];
    }

    /**
     * Set data via modal.setData or directly
     */
    setData(data = {}) {
        const files = data.files || [];
        const folders = data.folders || [];
        const folderId = data.currentFolderId || null;
        this.initialize(files, folders, { folderId });
    }

    /**
     * Create a folder via API and select it
     */
    async _createFolder(name) {
        try {
            // optimistic UI: create a temporary folder id
            const tempId = 'tmp-' + Math.random().toString(36).slice(2,7);
            const newFolder = { id: tempId, name };
            this.folders.unshift(newFolder);
            this._renderFolders();

            // POST to API
            const res = await fetch('/api/v1/folders', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }) ,
                credentials: 'same-origin'
            });

            if (!res.ok) throw new Error('create folder failed');
            const created = await res.json();

            // Replace temporary with real data
            const idx = this.folders.findIndex(f => f.id === tempId);
            if (idx !== -1) this.folders[idx] = created;
            this._renderFolders();

            // Select created
            const btn = Array.from(this.folderOptions).find(b => b.getAttribute('data-folder-id') === (created.id || created.uuid || created.id));
            if (btn) this._selectFolderBtn(btn);

            // clear input
            const input = this.shadowRoot.getElementById('new-folder-input');
            if (input) input.value = '';

            this.dispatchEvent(new CustomEvent('upload-form:folder-created', { detail: created }));
            return created;
        } catch (e) {
            // rollback optimistic
            this.folders = this.folders.filter(f => !f.id.startsWith('tmp-'));
            this._renderFolders();
            this.dispatchEvent(new CustomEvent('upload-form:folder-create-error', { detail: { message: e.message } }));
            return null;
        }
    }

    /**
     * Trigger a submit event with current selection
     */
    submit(detail = null) {
        const payload = detail || { destination: this.getDestinationFolder(), files: this.files };
        // Standard submit event
        this.dispatchEvent(new CustomEvent('submit', { detail: payload }));
        // Legacy event name
        this.dispatchEvent(new CustomEvent('upload-form:submit', { detail: payload }));
    }

    _selectFolderBtn(btn) {
        this.folderOptions.forEach(opt => opt.classList.remove('active'));
        btn.classList.add('active');
        const id = btn.getAttribute('data-folder-id');
        this.currentFolderId = id;
        const input = this.shadowRoot.getElementById('new-folder-input');
        if (input) input.value = '';
    }
}

customElements.define('hc-upload-form', HCUploadForm);
export { HCUploadForm };
