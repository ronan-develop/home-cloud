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

import '../components/hc-folder-list.js';
import { apiFetch } from './api.js';

const UPLOAD_API_ROUTE = '/api/v1/files';
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
    // Force critical layout styles inline — CSS class may load after injection
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px)';

    const card = document.createElement('div');
    card.className = 'upload-modal-card';
    card.style.cssText = [
        'position:relative',
        'width:100%',
        'max-width:480px',
        'margin:1rem',
        'border-radius:1.6rem',
        'overflow:hidden',
        'background:rgba(255,255,255,0.08)',
        'backdrop-filter:blur(24px) saturate(180%)',
        'border:1px solid rgba(255,255,255,0.15)',
        'box-shadow:0 8px 32px rgba(0,0,0,0.4)',
    ].join(';');

    const content = document.createElement('div');
    content.className = 'upload-modal-content';
    content.style.cssText = 'position:relative;z-index:10;padding:1.5rem;color:rgba(255,255,255,0.9);font-family:inherit';

    // Title
    const title = document.createElement('div');
    title.className = 'upload-modal-title';
    title.style.cssText = 'font-size:1.25rem;font-weight:600;color:rgba(255,255,255,0.95);margin-bottom:1.25rem';
    title.textContent = `Importer ${files.length} fichier${files.length > 1 ? 's' : ''}`;

    // Destination section
    const destSection = document.createElement('div');
    destSection.className = 'upload-destination';
    destSection.style.marginBottom = '1.25rem';

    const destLabel = document.createElement('label');
    destLabel.className = 'upload-destination-label';
    destLabel.style.cssText = 'font-size:0.75rem;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;display:block';
    destLabel.textContent = 'Destination';

    const folderListEl = document.createElement('hc-folder-list');
    folderListEl.id = 'hc-upload-folder-list';

    destSection.appendChild(destLabel);
    destSection.appendChild(folderListEl);

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
    actions.style.cssText = 'display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,0.08)';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'upload-btn upload-btn--cancel';
    cancelBtn.style.cssText = 'padding:0.6rem 1.25rem;border-radius:0.75rem;border:none;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);font-size:0.9rem;cursor:pointer';
    cancelBtn.textContent = 'Annuler';
    cancelBtn.id = 'hc-upload-cancel-btn';

    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.className = 'upload-btn upload-btn--submit';
    submitBtn.style.cssText = 'padding:0.6rem 1.25rem;border-radius:0.75rem;border:none;background:rgba(59,130,246,0.8);color:#fff;font-size:0.9rem;font-weight:600;cursor:pointer';
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
    item.style.cssText = 'padding:0.6rem 0;border-bottom:1px solid rgba(255,255,255,0.06)';

    const header = document.createElement('div');
    header.className = 'upload-item__header';
    header.style.cssText = 'display:flex;align-items:center;gap:0.5rem;font-size:0.82rem';

    const icon = document.createElement('span');
    icon.className = 'upload-item__icon';
    icon.textContent = '📎';

    const name = document.createElement('span');
    name.className = 'upload-item__name';
    name.style.cssText = 'flex:1;color:rgba(255,255,255,0.85);overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
    name.textContent = file.name;

    const size = document.createElement('span');
    size.className = 'upload-item__size';
    size.style.cssText = 'color:rgba(255,255,255,0.4);font-size:0.75rem;white-space:nowrap';
    size.textContent = formatFileSize(file.size);

    const status = document.createElement('span');
    status.className = 'upload-item__status upload-item__status--pending';
    status.style.cssText = 'color:rgba(255,255,255,0.4);font-size:0.75rem;white-space:nowrap';
    status.textContent = 'En attente';

    header.appendChild(icon);
    header.appendChild(name);
    header.appendChild(size);
    header.appendChild(status);

    const progress = document.createElement('div');
    progress.className = 'upload-item__progress';
    progress.style.cssText = 'height:2px;background:rgba(255,255,255,0.08);border-radius:1px;margin-top:0.35rem;overflow:hidden';

    const bar = document.createElement('div');
    bar.className = 'upload-item__bar';
    bar.style.cssText = 'height:100%;background:rgba(59,130,246,0.7);border-radius:1px;transition:width 0.3s ease;width:0%';

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

    // Create & open modal
    const overlay = createModalOverlay(files, folderId, folders || []);
    document.body.appendChild(overlay);

    // Initialize hc-folder-list component with available folders
    const folderListEl = overlay.querySelector('#hc-upload-folder-list');
    folderListEl.setFolders(folders || []);
    if (folderId) folderListEl.setSelected(folderId);

    // Get folder selection on submit — delegate to hc-folder-list
    const getDestFolder = () => folderListEl.getSelected();

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
    document.addEventListener('hc:files-selected', async (event) => {
        const { files } = event.detail;
        if (!files || !Array.isArray(files)) {
            console.warn('[UploadModal] Invalid files:', files);
            return;
        }

        // Fetch available folders from API via apiFetch (gère le token automatiquement)
        let folders = [];
        try {
            const res = await apiFetch('/api/v1/folders', {
                credentials: 'same-origin',
            });
            if (res.ok) {
                const json = await res.json();
                // API Platform retourne un tableau simple avec Accept: application/json
                // Supporte tous les formats de réponse possibles
                if (Array.isArray(json)) {
                    folders = json;
                } else {
                    const candidates = ['hydra:member', 'member', 'items', 'data'];
                    for (const key of candidates) {
                        if (Array.isArray(json[key])) { folders = json[key]; break; }
                    }
                }
            }
        } catch (err) {
            console.warn('[UploadModal] Could not fetch folders:', err);
        }

        openUploadModal(files, { folders }).catch(err => {
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
