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
import { declareBatch, createBatchPoller } from './upload-batch.js';
import { createEtaTracker, formatSpeed, formatEta } from './upload-eta.js';

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
 * POST JSON vers l'API (déclaration de lot), avec auth centralisée (apiFetch).
 */
async function postBatchJson(url, payload) {
    const res = await apiFetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error(`Batch request failed: ${res.status}`);
    return res.json();
}

/**
 * GET l'avancement d'un lot.
 */
async function fetchBatchStatus(batchId) {
    const res = await apiFetch(`/api/v1/uploads/${batchId}/status`, { method: 'GET' });
    if (!res.ok) throw new Error(`Batch status failed: ${res.status}`);
    return res.json();
}

/**
 * Suit un lot deferred : toast quand c'est prêt. S'arrête si l'onglet est caché
 * (l'email prend le relais), à la complétion ou au timeout (gérés par le poller).
 */
function startBatchTracking(batchId) {
    const poller = createBatchPoller({
        batchId,
        fetchStatus: fetchBatchStatus,
        onComplete: () => {
            document.removeEventListener('visibilitychange', onHidden);
            window.showToast?.('Vos fichiers sont prêts dans la galerie', 'success');
        },
    });

    function onHidden() {
        if (document.hidden) {
            poller.stop();
            document.removeEventListener('visibilitychange', onHidden);
        }
    }
    document.addEventListener('visibilitychange', onHidden);

    poller.start();
    return poller;
}

/**
 * Create uploadFn for upload queue.
 * Wraps XHR to support progress callbacks.
 *
 * @param {string} token - Bearer token for auth
 * @param {string} folderId - Optional folder ID
 * @param {string} newFolderName - Optional new folder name
 * @param {string} batchId - Optional upload batch ID (corrèle les fichiers d'un même envoi)
 * @param {boolean} [processSync] - Traitement média synchrone (#339, import direct dans un album :
 *   le mediaId doit être connu immédiatement dans la réponse)
 * @returns {Function} (file, metadata, onProgress) => Promise
 */
function createUploadFn(token, folderId, newFolderName, batchId, processSync) {
    return (file, metadata, onProgress) => {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();

            formData.append('file', file);
            formData.append('ownerId', window.HC?.userId || '');
            if (folderId) formData.append('folderId', folderId);
            if (newFolderName) formData.append('newFolderName', newFolderName);
            if (batchId) formData.append('batchId', batchId);
            if (processSync) formData.append('processSync', '1');
            // Dossier local glissé-déposé (#238) : recrée l'arborescence côté serveur
            if (file._hcRelativePath) formData.append('relativePath', file._hcRelativePath);

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
 * Associe un média fraîchement uploadé (processSync) à un album (#339).
 *
 * @param {string} albumId
 * @param {string} mediaId
 */
async function addMediaToAlbum(albumId, mediaId) {
    const res = await apiFetch(`/api/v1/albums/${albumId}/medias`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mediaId }),
    });
    if (!res.ok) throw new Error(`Album association failed: ${res.status}`);
    return res.json();
}

/**
 * Render upload modal overlay + card.
 *
 * @param {File[]} files
 * @param {string} currentFolderId - Pre-selected folder
 * @param {Object} folders - Available folders {id, name, icon}[]
 * @param {boolean} [isAlbumMode] - Mode album (#339) : pas de sélecteur de destination
 * @returns {HTMLElement} overlay
 */
function createModalOverlay(files, currentFolderId, folders, isAlbumMode) {
    const overlay = document.createElement('div');
    overlay.className = 'upload-modal-overlay';
    overlay.id = 'hc-upload-modal-overlay';
    // Force critical layout styles inline — CSS class may load after injection
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45)';

    const card = document.createElement('div');
    card.className = 'upload-modal-card';
    card.style.cssText = [
        'position:relative',
        'width:100%',
        'max-width:480px',
        'margin:1rem',
        'border-radius:1.6rem',
        'overflow:hidden',
        'background:var(--hc-surface)',
        'border:1px solid var(--hc-border)',
        'box-shadow:var(--hc-shadow-lg)',
    ].join(';');

    const content = document.createElement('div');
    content.className = 'upload-modal-content';
    content.style.cssText = 'position:relative;z-index:10;padding:1.5rem;color:var(--hc-text);font-family:inherit';

    // Title
    const title = document.createElement('div');
    title.className = 'upload-modal-title';
    title.style.cssText = 'font-size:1.25rem;font-weight:600;color:var(--hc-text);margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem';
    title.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>'
        + `<span>Importer ${files.length} fichier${files.length > 1 ? 's' : ''}</span>`;

    // Destination section — absente en mode album (#339) : pas de dossier
    // à choisir, le fichier est associé directement à l'album.
    let destSection = null;
    if (!isAlbumMode) {
        destSection = document.createElement('div');
        destSection.className = 'upload-destination';
        destSection.style.marginBottom = '1.25rem';

        const destLabel = document.createElement('label');
        destLabel.className = 'upload-destination-label';
        destLabel.style.cssText = 'font-size:0.75rem;font-weight:600;color:var(--hc-text-2);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;display:block';
        destLabel.textContent = 'Destination';

        const folderListEl = document.createElement('hc-folder-list');
        folderListEl.id = 'hc-upload-folder-list';

        destSection.appendChild(destLabel);
        destSection.appendChild(folderListEl);
    }

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
    actions.style.cssText = 'display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--hc-border)';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'upload-btn upload-btn--cancel';
    cancelBtn.style.cssText = 'padding:0.6rem 1.25rem;border-radius:0.75rem;border:none;background:var(--hc-surface-2);color:var(--hc-text-2);font-size:0.9rem;cursor:pointer';
    cancelBtn.textContent = 'Annuler';
    cancelBtn.id = 'hc-upload-cancel-btn';

    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.className = 'upload-btn upload-btn--submit';
    submitBtn.style.cssText = 'padding:0.6rem 1.25rem;border-radius:0.75rem;border:none;background:var(--hc-accent);color:#fff;font-size:0.9rem;font-weight:600;cursor:pointer';
    submitBtn.textContent = 'Uploader';
    submitBtn.id = 'hc-upload-submit-btn';

    actions.appendChild(cancelBtn);
    actions.appendChild(submitBtn);

    content.appendChild(title);
    if (destSection) content.appendChild(destSection);
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
    item.style.cssText = 'padding:0.6rem 0;border-bottom:1px solid var(--hc-border)';

    const header = document.createElement('div');
    header.className = 'upload-item__header';
    header.style.cssText = 'display:flex;align-items:center;gap:0.5rem;font-size:0.82rem';

    const icon = document.createElement('span');
    icon.className = 'upload-item__icon';
    icon.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--hc-text-2)"><path d="M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.101m-.758-4.899a4 4 0 0 0 5.658 0l4-4a4 4 0 0 0-5.656-5.656l-1.1 1.1"/></svg>';

    const name = document.createElement('span');
    name.className = 'upload-item__name';
    name.style.cssText = 'flex:1;color:var(--hc-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
    name.textContent = file.name;

    const size = document.createElement('span');
    size.className = 'upload-item__size';
    size.style.cssText = 'color:var(--hc-text-3);font-size:0.75rem;white-space:nowrap';
    size.textContent = formatFileSize(file.size);

    const status = document.createElement('span');
    status.className = 'upload-item__status upload-item__status--pending';
    status.style.cssText = 'color:var(--hc-text-3);font-size:0.75rem;white-space:nowrap';
    status.textContent = 'En attente';

    header.appendChild(icon);
    header.appendChild(name);
    header.appendChild(size);
    header.appendChild(status);

    const progress = document.createElement('div');
    progress.className = 'upload-item__progress';
    progress.style.cssText = 'height:2px;background:var(--hc-border);border-radius:1px;margin-top:0.35rem;overflow:hidden';

    const bar = document.createElement('div');
    bar.className = 'upload-item__bar';
    bar.style.cssText = 'height:100%;border-radius:1px;transition:width 0.3s ease;width:0%';

    progress.appendChild(bar);

    const meta = document.createElement('div');
    meta.className = 'upload-item__meta';
    meta.style.cssText = 'margin-top:0.25rem';

    item.appendChild(header);
    item.appendChild(progress);
    item.appendChild(meta);

    return item;
}

/**
 * Format file size for display.
 * 
 * @param {number} bytes
 * @returns {string}
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 o';
    const k = 1024;
    const sizes = ['o', 'Ko', 'Mo', 'Go'];
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
 * @param {{loaded: number, total: number, speedBytesPerSec: number, etaSeconds: number|null}} [volumeInfo]
 */
function updateUploadItem(itemEl, state, progress, error, volumeInfo) {
    // Update state class
    const stateClass = state.toLowerCase();
    itemEl.className = `upload-item upload-item--${stateClass}`;

    // Update status text
    const statusEl = itemEl.querySelector('.upload-item__status');
    const statusMap = {
        uploading: 'En cours',
        completed: 'Terminé',
        done: 'Terminé',
        error: 'Erreur',
        cancelled: 'Annulé',
        pending: 'En attente',
    };
    statusEl.textContent = statusMap[stateClass] || state;
    statusEl.className = `upload-item__status upload-item__status--${stateClass}`;

    // Update progress bar
    const barEl = itemEl.querySelector('.upload-item__bar');
    barEl.style.width = `${progress || 0}%`;

    // Update volume/speed/ETA line
    const metaEl = itemEl.querySelector('.upload-item__meta');
    if (metaEl) {
        if (state === 'uploading' && volumeInfo) {
            const { loaded, total, speedBytesPerSec, etaSeconds } = volumeInfo;
            metaEl.textContent = `${formatFileSize(loaded)} / ${formatFileSize(total)} — ${formatSpeed(speedBytesPerSec)} — ${formatEta(etaSeconds)}`;
        } else {
            metaEl.textContent = '';
        }
    }

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
 * @param {Object} options - {folderId, folders, albumId}
 * @param {string} [options.albumId] - Mode album (#339) : pas de sélecteur de
 *   destination, chaque upload est traité en synchrone (processSync) puis
 *   associé à cet album via son mediaId.
 */
async function openUploadModal(files, options = {}) {
    const { folderId, folders, albumId } = options;
    const isAlbumMode = Boolean(albumId);

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
    const overlay = createModalOverlay(files, folderId, folders || [], isAlbumMode);
    document.body.appendChild(overlay);

    // Initialize hc-folder-list component with available folders — absent en mode album
    const folderListEl = overlay.querySelector('#hc-upload-folder-list');
    if (folderListEl) {
        folderListEl.setFolders(folders || []);
        if (folderId) folderListEl.setSelected(folderId);
    }

    // Get folder selection on submit — mode album : pas de dossier à choisir,
    // le backend en assigne un par défaut (DefaultFolderService).
    const getDestFolder = () => (isAlbumMode ? { id: null, isNew: false } : folderListEl.getSelected());

    const fileListEl = overlay.querySelector('#hc-upload-file-list');
    const itemElements = new Map();

    // Map file → queue item element. Indexé par la référence de l'objet File
    // (et non son nom) : deux fichiers sélectionnés portant le même nom
    // (ex. IMG_0001.jpg de deux dossiers différents) sont deux références
    // distinctes, chacune avec sa propre barre — indexer par nom les ferait
    // collisionner dans la Map (#239).
    files.forEach((file, idx) => {
        const itemEl = fileListEl.children[idx];
        itemElements.set(file, itemEl);
    });

    // Tracker de vitesse/ETA par fichier, indexé comme itemElements (#336)
    const etaTrackers = new Map();
    files.forEach((file) => {
        etaTrackers.set(file, createEtaTracker());
    });

    // Mode album (#339) : promesses d'association média→album en cours,
    // à attendre avant d'afficher le succès / recharger la page.
    const pendingAlbumAssociations = [];

    // Create upload queue with callbacks for UI updates
    currentUploadQueue = createUploadQueueFn({
        maxConcurrent: 3,
        onProgress: (file, progress) => {
            const itemEl = itemElements.get(file);
            if (itemEl) {
                const loaded = progress.loaded || 0;
                const total = progress.total || file.size;
                const tracker = etaTrackers.get(file);
                const { speedBytesPerSec, etaSeconds } = tracker.sample(loaded, total);
                updateUploadItem(itemEl, 'uploading', progress.progress || 0, null, {
                    loaded,
                    total,
                    speedBytesPerSec,
                    etaSeconds,
                });
            }
        },
        onComplete: (file, response) => {
            const itemEl = itemElements.get(file);
            if (itemEl) {
                updateUploadItem(itemEl, 'completed', 100);
            }
            // Mode album (#339) : le fichier a été traité en synchrone
            // (processSync), le mediaId est donc déjà connu — l'associer à
            // l'album. Un fichier sans média possible (ex: PDF) n'a pas de
            // mediaId : rien à associer, ce n'est pas une erreur. La promesse
            // est stockée (pas juste catchée) pour que le submit handler
            // puisse attendre la fin de TOUTES les associations avant
            // d'afficher le succès / recharger la page — sinon un lot de
            // plusieurs fichiers en concurrence pourrait recharger la page
            // avant que la dernière association ait fini côté serveur.
            if (isAlbumMode && response?.mediaId) {
                const p = addMediaToAlbum(albumId, response.mediaId).catch((err) => {
                    console.error('[UploadModal] Album association failed:', err);
                });
                pendingAlbumAssociations.push(p);
            }
        },
        onError: (file, error) => {
            const itemEl = itemElements.get(file);
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
            // Déclarer le lot : le serveur décide immediate vs deferred et renvoie
            // le batchId à joindre à chaque upload. Best-effort — si la déclaration
            // échoue, on uploade quand même sans lot (traitement immédiat côté serveur).
            // Mode album (#339) : jamais de lot deferred — processSync l'exige déjà
            // côté backend (garde-fou 400 sinon), et l'utilisateur attend la
            // confirmation immédiate de l'ajout à l'album.
            let batchId = null;
            let batchMode = 'immediate';
            if (!isAlbumMode) {
                try {
                    const declared = await declareBatch(files, postBatchJson);
                    batchId = declared?.batchId ?? null;
                    batchMode = declared?.mode ?? 'immediate';
                } catch (e) {
                    console.debug('Batch declaration failed, uploading without batch', e);
                }
            }

            // Create uploadFn with selected folder + batch
            const uploadFn = createUploadFn(
                currentToken,
                destFolder.isNew ? null : destFolder.id,
                destFolder.isNew ? destFolder.name : null,
                batchId,
                isAlbumMode,
            );

            // Start queue processing with uploadFn
            await currentUploadQueue.process(uploadFn);

            // Mode album : attendre que toutes les associations média→album
            // (déclenchées par onComplete) soient terminées avant d'afficher
            // le succès et de recharger la page — sinon la dernière
            // association pourrait ne pas être visible après le reload.
            if (isAlbumMode) {
                await Promise.all(pendingAlbumAssociations);
            }

            // Check results
            const stats = currentUploadQueue.getStats();
            if (stats.error > 0) {
                window.showToast?.(
                    `${stats.error} fichier(s) en erreur, ${stats.completed} réussi(s)`,
                    'warning'
                );
            } else if (batchMode === 'deferred' && batchId) {
                // Lot lourd : le transfert est fini, le traitement média continue
                // en tâche de fond (worker). On prévient et on suit par polling.
                window.showToast?.('Envoi terminé — traitement en cours, vous recevrez un email quand tout sera prêt', 'success');
                startBatchTracking(batchId);
            } else {
                window.showToast?.('Tous les fichiers ont été importés', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }

            closeUploadModal();
        } catch (err) {
            console.error('Upload error:', err);
            window.showToast?.('Erreur lors de l\'upload : ' + err.message, 'error');
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
        const { files, folderId, albumId } = event.detail;
        if (!files || !Array.isArray(files)) {
            console.warn('[UploadModal] Invalid files:', files);
            return;
        }

        // Mode album (#339) : pas de sélecteur de destination, donc pas
        // besoin de récupérer la liste des dossiers.
        if (albumId) {
            openUploadModal(files, { albumId }).catch(err => {
                console.error('[UploadModal] Error opening modal:', err);
            });
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

        openUploadModal(files, { folders, folderId: folderId || '' }).catch(err => {
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
