/**
 * HomeCloud Upload Modal — Phase 5d
 * 
 * Orchestrates multi-file upload workflow:
 * 1. Listens for 'hc:files-selected' event (from file input or drag & drop)
 * 2. Opens Liquid Glass modal with folder selector
 * 3. Creates upload queue with XHR-based uploadFn
 * 4. Renders file list with progress bars
 * 5. Handles cancel/submit/retry actions
 * 6. Shows completion toast and reloads page
 */

const UPLOAD_API_ROUTE = '/_api_/v1/files_post';
const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024; // 5 GB

let currentUploadQueue = null;
let currentToken = null;

// Lazy import
let createUploadQueue = null;

async function getCreateUploadQueue() {
    if (!createUploadQueue) {
        const module = await import('./upload-queue.js');
        createUploadQueue = module.createUploadQueue;
    }
    return createUploadQueue;
}

/**
 * Create uploadFn for upload queue.
 * Wraps XHR to support progress callbacks.
 * 
 * @param {string} token - Bearer token for auth
 * @param {string} folderId - Optional folder ID
 * @param {string} newFolderName - Optional new folder name
 * @returns {Function} (file, metadata, onProgress) => Promise
 */
function createUploadFn(token, folderId, newFolderName) {
    return (file, metadata, onProgress) => {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            
            formData.append('file', file);
            if (folderId) formData.append('folderId', folderId);
            if (newFolderName) formData.append('newFolderName', newFolderName);

            // Progress tracking
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        onProgress({ 
                            loaded: e.loaded, 
                            total: e.total, 
                            progress: Math.round((e.loaded / e.total) * 100)
                        });
                    }
                });
            }

            xhr.addEventListener('load', () => {
                if (xhr.status === 201 || xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else if (xhr.status === 401) {
                    reject(new Error('Unauthorized'));
                } else if (xhr.status === 413) {
                    reject(new Error('File too large'));
                } else {
                    reject(new Error(`Upload failed: ${xhr.status}`));
                }
            });

            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });

            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });

            // Send request with auth header
            xhr.open('POST', UPLOAD_API_ROUTE, true);
            if (token) {
                xhr.setRequestHeader('Authorization', `Bearer ${token}`);
            }
            xhr.send(formData);

            // Store xhr ref for manual cancellation if needed
            metadata._xhr = xhr;
        });
    };
}

/**
 * Render upload modal overlay + card.
 * 
 * @param {File[]} files
 * @param {string} currentFolderId - Pre-selected folder
 * @param {Object} folders - Available folders {id, name, icon}[]
 * @returns {HTMLElement} overlay
 */
function createModalOverlay(files, currentFolderId, folders) {
    const overlay = document.createElement('div');
    overlay.className = 'upload-modal-overlay';
    overlay.id = 'hc-upload-modal-overlay';

    const card = document.createElement('div');
    card.className = 'upload-modal-card';

    const content = document.createElement('div');
    content.className = 'upload-modal-content';

    // Title
    const title = document.createElement('div');
    title.className = 'upload-modal-title';
    title.textContent = `Importer ${files.length} fichier${files.length > 1 ? 's' : ''}`;

    // Destination section
    const destSection = document.createElement('div');
    destSection.className = 'upload-destination';

    const destLabel = document.createElement('label');
    destLabel.className = 'upload-destination-label';
    destLabel.textContent = 'Destination';

    const folderList = document.createElement('div');
    folderList.className = 'upload-folder-list';

    // Render folder options
    const folderOptions = [];
    if (folders && folders.length > 0) {
        folders.forEach(folder => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'upload-folder-option';
            if (folder.id === currentFolderId) btn.classList.add('active');
            btn.setAttribute('data-icon', folder.icon || '📁');
            btn.setAttribute('data-folder-id', folder.id);
            btn.textContent = folder.name;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                folderOptions.forEach(opt => opt.classList.remove('active'));
                btn.classList.add('active');
            });

            folderList.appendChild(btn);
            folderOptions.push(btn);
        });
    }

    // New folder input
    const newFolderContainer = document.createElement('div');
    newFolderContainer.className = 'upload-new-folder-container';

    const newFolderInput = document.createElement('input');
    newFolderInput.type = 'text';
    newFolderInput.className = 'upload-new-folder-input';
    newFolderInput.placeholder = 'Ou créer un dossier…';
    newFolderInput.id = 'hc-upload-new-folder-input';

    newFolderContainer.appendChild(newFolderInput);

    destSection.appendChild(destLabel);
    destSection.appendChild(folderList);
    destSection.appendChild(newFolderContainer);

    // File list
    const fileListEl = document.createElement('div');
    fileListEl.className = 'upload-file-list';
    fileListEl.id = 'hc-upload-file-list';

    files.forEach(file => {
        const item = createUploadItem(file);
        fileListEl.appendChild(item);
    });

    // Actions
    const actions = document.createElement('div');
    actions.className = 'upload-actions';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'upload-btn upload-btn--cancel';
    cancelBtn.textContent = 'Annuler';
    cancelBtn.id = 'hc-upload-cancel-btn';

    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.className = 'upload-btn upload-btn--submit';
    submitBtn.textContent = '⬆️ Uploader';
    submitBtn.id = 'hc-upload-submit-btn';

    actions.appendChild(cancelBtn);
    actions.appendChild(submitBtn);

    content.appendChild(title);
    content.appendChild(destSection);
    content.appendChild(fileListEl);
    content.appendChild(actions);

    card.appendChild(content);
    overlay.appendChild(card);

    return overlay;
}

/**
 * Create file item element (will be updated by queue callbacks).
 * 
 * @param {File} file
 * @returns {HTMLElement}
 */
function createUploadItem(file) {
    const item = document.createElement('div');
    item.className = 'upload-item upload-item--pending';
    item.setAttribute('data-file-name', file.name);

    const header = document.createElement('div');
    header.className = 'upload-item__header';

    const icon = document.createElement('span');
    icon.className = 'upload-item__icon';
    icon.textContent = '📎';

    const name = document.createElement('span');
    name.className = 'upload-item__name';
    name.textContent = file.name;

    const size = document.createElement('span');
    size.className = 'upload-item__size';
    size.textContent = formatFileSize(file.size);

    const status = document.createElement('span');
    status.className = 'upload-item__status upload-item__status--pending';
    status.textContent = 'En attente';

    header.appendChild(icon);
    header.appendChild(name);
    header.appendChild(size);
    header.appendChild(status);

    const progress = document.createElement('div');
    progress.className = 'upload-item__progress';

    const bar = document.createElement('div');
    bar.className = 'upload-item__bar';
    bar.style.width = '0%';

    progress.appendChild(bar);

    item.appendChild(header);
    item.appendChild(progress);

    return item;
}

/**
 * Format file size for display.
 * 
 * @param {number} bytes
 * @returns {string}
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

/**
 * Update item UI based on queue state.
 * 
 * @param {HTMLElement} itemEl
 * @param {string} state
 * @param {number} progress
 * @param {string} error
 */
function updateUploadItem(itemEl, state, progress, error) {
    // Update state class
    const stateClass = state.toLowerCase();
    itemEl.className = `upload-item upload-item--${stateClass}`;

    // Update status text
    const statusEl = itemEl.querySelector('.upload-item__status');
    const statusMap = {
        uploading: '⬆️ En cours',
        completed: '✅ Terminé',
        done: '✅ Terminé',
        error: '❌ Erreur',
        cancelled: '⏹️ Annulé',
        pending: '⏳ En attente',
    };
    statusEl.textContent = statusMap[stateClass] || state;
    statusEl.className = `upload-item__status upload-item__status--${stateClass}`;

    // Update progress bar
    const barEl = itemEl.querySelector('.upload-item__bar');
    barEl.style.width = `${progress || 0}%`;

    // Show error message if needed
    if (state === 'error' && error) {
        let errorMsg = itemEl.querySelector('.upload-item__error-msg');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'upload-item__error-msg';
            errorMsg.style.fontSize = '0.7rem';
            errorMsg.style.color = '#f87171';
            errorMsg.style.marginTop = '0.25rem';
            itemEl.appendChild(errorMsg);
        }
        errorMsg.textContent = error;
    }
}

/**
 * Initialize upload modal and queue.
 * Called when 'hc:files-selected' fires.
 * 
 * @param {File[]} files
 * @param {Object} options - {folderId, folders}
 */
async function openUploadModal(files, options = {}) {
    const { folderId, folders } = options;

    // Close previous modal if any
    closeUploadModal();

    // Get auth token
    try {
        currentToken = await (window.HC?.getToken?.() || Promise.resolve(''));
    } catch (err) {
        console.warn('Failed to get token:', err);
        currentToken = '';
    }

    // Import createUploadQueue
    const createUploadQueueFn = await getCreateUploadQueue();

    // Create & open modal — insert as first child of body (before script tags)
    const overlay = createModalOverlay(files, folderId, folders || []);
    document.body.insertBefore(overlay, document.body.firstChild);

    // Get folder selection on submit
    const getDestFolder = () => {
        const selected = overlay.querySelector('.upload-folder-option.active');
        if (selected) {
            return {
                id: selected.getAttribute('data-folder-id'),
                isNew: false,
            };
        }
        const newFolderInput = overlay.querySelector('#hc-upload-new-folder-input');
        if (newFolderInput.value) {
            return {
                name: newFolderInput.value,
                isNew: true,
            };
        }
        return null;
    };

    const fileListEl = overlay.querySelector('#hc-upload-file-list');
    const itemElements = new Map();

    // Map file → queue item element
    files.forEach((file, idx) => {
        const itemEl = fileListEl.children[idx];
        itemElements.set(file.name, itemEl);
    });

    // Create upload queue with callbacks for UI updates
    currentUploadQueue = createUploadQueueFn({
        maxConcurrent: 3,
        onProgress: (file, progress) => {
            const itemEl = itemElements.get(file.name);
            if (itemEl) {
                updateUploadItem(itemEl, 'uploading', progress.progress || 0);
            }
        },
        onComplete: (file, response) => {
            const itemEl = itemElements.get(file.name);
            if (itemEl) {
                updateUploadItem(itemEl, 'completed', 100);
            }
        },
        onError: (file, error) => {
            const itemEl = itemElements.get(file.name);
            if (itemEl) {
                updateUploadItem(itemEl, 'error', 0, error.message || 'Unknown error');
            }
        },
    });

    // Enqueue all files
    currentUploadQueue.enqueue(files, {});

    // Submit button
    const submitBtn = overlay.querySelector('#hc-upload-submit-btn');
    submitBtn.addEventListener('click', async () => {
        submitBtn.disabled = true;
        const destFolder = getDestFolder();

        if (!destFolder) {
            window.showToast?.('Veuillez sélectionner une destination', 'error');
            submitBtn.disabled = false;
            return;
        }

        try {
            // Create uploadFn with selected folder
            const uploadFn = createUploadFn(
                currentToken,
                destFolder.isNew ? null : destFolder.id,
                destFolder.isNew ? destFolder.name : null
            );

            // Start queue processing with uploadFn
            await currentUploadQueue.process(uploadFn);

            // Check results
            const stats = currentUploadQueue.getStats();
            if (stats.error === 0) {
                window.showToast?.('✅ Tous les fichiers ont été importés', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                window.showToast?.(
                    `⚠️ ${stats.error} fichier(s) en erreur, ${stats.completed} réussi(s)`,
                    'error'
                );
            }

            closeUploadModal();
        } catch (err) {
            console.error('Upload error:', err);
            window.showToast?.('❌ Erreur lors de l\'upload: ' + err.message, 'error');
            submitBtn.disabled = false;
        }
    });

    // Cancel button
    const cancelBtn = overlay.querySelector('#hc-upload-cancel-btn');
    cancelBtn.addEventListener('click', () => {
        currentUploadQueue?.cancelAll();
        closeUploadModal();
    });

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            currentUploadQueue?.cancelAll();
            closeUploadModal();
        }
    });
}

/**
 * Close & cleanup upload modal.
 */
function closeUploadModal() {
    const overlay = document.getElementById('hc-upload-modal-overlay');
    if (overlay) {
        overlay.remove();
    }
    if (currentUploadQueue) {
        currentUploadQueue.destroy();
        currentUploadQueue = null;
    }
}

/**
 * Initialize module: listen for 'hc:files-selected' event.
 */
export function initUploadModal() {
    document.addEventListener('hc:files-selected', (event) => {
        const { files } = event.detail;
        if (!files || !Array.isArray(files)) {
            console.warn('[UploadModal] Invalid files:', files);
            return;
        }

        openUploadModal(files, {
            folderId: 'root',
        }).catch(err => {
            console.error('[UploadModal] Error opening modal:', err);
        });
    });
}

/**
 * Export for testing.
 */
export {
    createUploadFn,
    createModalOverlay,
    createUploadItem,
    formatFileSize,
    updateUploadItem,
    openUploadModal,
    closeUploadModal,
};
