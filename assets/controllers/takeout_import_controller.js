import { Controller } from '@hotwired/stimulus';
import { apiFetch } from '../js/api.js';
import { createBatchPoller } from '../js/upload-batch.js';
import { uploadFileInChunks } from '../js/chunked-upload.js';

const IMPORTS_ROUTE = '/api/v1/takeout-imports';

/**
 * Page d'import Google Photos Takeout (#327) : upload du/des ZIP puis
 * suivi de la progression par polling (barre + compteurs), en réutilisant
 * le même poller que le suivi de lot d'upload classique (upload-batch.js) —
 * même stratégie de backoff/timeout, pas de duplication de cette logique.
 *
 * Upload séquentiel en 3 étapes (#466) : un seul gros POST avec tous les ZIP
 * dépassait la limite de taille de requête du serveur mutualisé (413) dès
 * qu'un utilisateur sélectionnait plusieurs Takeout volumineux d'un coup.
 * L'utilisateur sélectionne toujours tout en une fois ; le front enchaîne
 * ensuite create → un POST par ZIP → start, chaque fichier restant bien sous
 * la limite.
 */
export default class extends Controller {
    static targets = [
        'input', 'dropzone', 'form', 'fileList', 'progress', 'bar', 'status', 'counts', 'error', 'submit',
        'fileProgressWrapper', 'fileBar', 'fileStatus', 'patienceMessage',
    ];

    // #481 : messages qui tournent pendant l'upload, pour rassurer sur un
    // transfert long (plusieurs minutes avec un gros ZIP en chunks) et
    // rappeler de garder l'onglet ouvert — les chunks déjà envoyés restent
    // orphelins côté serveur si l'utilisateur ferme trop tôt.
    static PATIENCE_MESSAGES = [
        'Ne fermez pas cet onglet pendant l\'envoi…',
        'L\'envoi continue en arrière-plan, merci de patienter…',
        'Les gros fichiers peuvent prendre plusieurs minutes…',
        'Toujours en cours, aucune action nécessaire de votre part…',
    ];
    static PATIENCE_MESSAGE_INTERVAL_MS = 4000;

    connect() {
        this._poller = null;
        this._patienceInterval = null;
    }

    disconnect() {
        this._poller?.stop();
        this._stopPatienceMessages();
    }

    triggerFilePicker() {
        this.inputTarget.click();
    }

    onFilesSelected() {
        const files = Array.from(this.inputTarget.files || []);
        this.submitTarget.disabled = files.length === 0;

        // Confirmation visuelle des ZIP sélectionnés avant l'envoi (#458) —
        // utile en particulier pour un import multi-ZIP (Google Takeout
        // découpe souvent l'export en plusieurs archives), où il n'y avait
        // jusqu'ici aucun moyen de vérifier la sélection avant de démarrer.
        this.fileListTarget.innerHTML = '';
        files.forEach((file) => {
            const li = document.createElement('li');
            li.textContent = file.name;
            this.fileListTarget.appendChild(li);
        });
    }

    async submit(event) {
        event.preventDefault();

        const files = Array.from(this.inputTarget.files || []);
        if (files.length === 0) {
            return;
        }

        this.submitTarget.disabled = true;
        this._hideError();

        // Zone de progression affichée dès le début de l'upload (#477) —
        // avec des ZIP Takeout de plusieurs GB découpés en tranches (#466),
        // l'envoi seul peut durer plusieurs minutes ; un bouton simplement
        // grisé sans aucun autre retour ressemblait à un gel de l'interface
        // (constaté en conditions réelles avec 11 fichiers).
        //
        // Deux barres distinctes : la globale suit les fichiers déjà envoyés
        // en entier, la seconde suit les chunks du fichier en cours — un ZIP
        // de plusieurs GB tient à lui seul plusieurs dizaines de chunks de
        // 50 Mo (chunked-upload.js), la barre globale seule restait quasi
        // figée tout ce temps (constaté en conditions réelles).
        this._showUploadProgress(files.length);

        try {
            const importId = await this._createImport();
            for (const [index, file] of files.entries()) {
                await this._uploadFile(importId, file, (meta) => {
                    this._renderFileProgress(file.name, meta.chunkIndex + 1, meta.totalChunks);
                });
                this._renderGlobalProgress(index + 1, files.length);
            }
            this.fileProgressWrapperTarget.hidden = true;
            await this._startImport(importId);

            this._stopPatienceMessages();
            this._startPolling(importId);
        } catch (err) {
            this._hideUploadProgress();
            this._showError(err.message || 'Erreur lors de l\'envoi');
            this.submitTarget.disabled = false;
        }
    }

    _showUploadProgress(totalFiles) {
        this.formTarget.hidden = true;
        this.progressTarget.hidden = false;
        this.fileProgressWrapperTarget.hidden = true;
        this._renderGlobalProgress(0, totalFiles);
        this._startPatienceMessages();
    }

    _hideUploadProgress() {
        this.formTarget.hidden = false;
        this.progressTarget.hidden = true;
        this.fileProgressWrapperTarget.hidden = true;
        this._stopPatienceMessages();
    }

    _startPatienceMessages() {
        const messages = this.constructor.PATIENCE_MESSAGES;
        let index = 0;
        this.patienceMessageTarget.textContent = messages[index];
        this._patienceInterval = setInterval(() => {
            index = (index + 1) % messages.length;
            this.patienceMessageTarget.textContent = messages[index];
        }, this.constructor.PATIENCE_MESSAGE_INTERVAL_MS);
    }

    _stopPatienceMessages() {
        clearInterval(this._patienceInterval);
        this._patienceInterval = null;
        this.patienceMessageTarget.textContent = '';
    }

    _renderGlobalProgress(uploadedCount, totalCount) {
        const percent = Math.round((uploadedCount / totalCount) * 100);
        this.barTarget.style.width = `${percent}%`;
        this.barTarget.classList.remove('takeout-progress-bar--indeterminate');
        this.statusTarget.textContent = `Envoi de ${Math.min(uploadedCount + 1, totalCount)} / ${totalCount} fichiers…`;
        this.countsTarget.textContent = '';
    }

    // Barre masquée pour un fichier à chunk unique : elle sauterait
    // instantanément à 100% sans jamais montrer de progression réelle,
    // un simple clignotement inutile pour chaque petit fichier de la liste.
    _renderFileProgress(filename, uploadedChunks, totalChunks) {
        if (totalChunks <= 1) {
            this.fileProgressWrapperTarget.hidden = true;
            return;
        }
        this.fileProgressWrapperTarget.hidden = false;
        const percent = Math.round((uploadedChunks / totalChunks) * 100);
        this.fileBarTarget.style.width = `${percent}%`;
        this.fileStatusTarget.textContent = `${filename} — ${percent}%`;
    }

    async _createImport() {
        const res = await this._authenticatedFetch(IMPORTS_ROUTE, { method: 'POST' });
        if (!res.ok) {
            throw new Error(`Échec de la création de l'import (${res.status})`);
        }
        const data = await res.json();
        return data.id;
    }

    async _uploadFile(importId, file, onChunkUploaded) {
        await uploadFileInChunks(file, async (blob, meta) => {
            const formData = new FormData();
            formData.append('file', blob);
            formData.append('filename', meta.filename);
            formData.append('chunkIndex', String(meta.chunkIndex));
            formData.append('totalChunks', String(meta.totalChunks));

            const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/${importId}/files`, {
                method: 'POST',
                body: formData,
            });
            if (!res.ok) {
                throw new Error(`Échec de l'envoi de ${file.name} (${res.status})`);
            }
        }, { onChunkUploaded });
    }

    async _startImport(importId) {
        const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/${importId}/start`, { method: 'POST' });
        if (!res.ok) {
            throw new Error(`Échec du démarrage de l'import (${res.status})`);
        }
    }

    async _authenticatedFetch(url, options) {
        const token = await (window.HC?.getToken?.() || Promise.resolve(''));
        return fetch(url, {
            ...options,
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        });
    }

    _startPolling(importId) {
        this._poller = createBatchPoller({
            batchId: importId,
            fetchStatus: (id) => this._fetchImportStatus(id),
            // onProgress est appelé à CHAQUE tick (pending, extracting,
            // processing, jusqu'au terminal) — sans lui la barre restait
            // invisible pendant toute la durée de l'import, onComplete
            // n'étant notifié qu'au tout dernier tick (#458).
            onProgress: (status) => this._renderStatus(status),
            onError: () => this._showError('Impossible de récupérer la progression'),
        });

        // Rendu immédiat (pending) avant le premier tick du poller, pour ne
        // pas laisser la barre vide pendant les premières secondes.
        this._fetchImportStatus(importId).then((status) => this._renderStatus(status));

        this._poller.start();
    }

    async _fetchImportStatus(importId) {
        const res = await apiFetch(`${IMPORTS_ROUTE}/${importId}`, { method: 'GET' });
        if (!res.ok) {
            throw new Error(`Statut indisponible (${res.status})`);
        }
        return res.json();
    }

    _renderStatus(status) {
        const total = status.totalMediaCount;
        const processed = status.processedCount || 0;

        // total encore inconnu (extraction en cours, avant le parsing) :
        // barre indéterminée plutôt qu'une fausse valeur à 0%.
        if (total === null || total === undefined || total === 0) {
            this.barTarget.style.width = '100%';
            this.barTarget.classList.add('takeout-progress-bar--indeterminate');
        } else {
            this.barTarget.classList.remove('takeout-progress-bar--indeterminate');
            const percent = Math.min(100, Math.round((processed / total) * 100));
            this.barTarget.style.width = `${percent}%`;
        }

        this.statusTarget.textContent = this._statusLabel(status.status);
        this.countsTarget.textContent = total
            ? `${processed} / ${total} médias traités`
            : '';

        if (status.status === 'completed') {
            this.countsTarget.textContent = `${status.mediaImportedCount} importé(s), ${status.duplicatesSkippedCount} doublon(s) ignoré(s)`;
            window.showToast?.('Import Google Photos terminé', 'success');
        } else if (status.status === 'failed') {
            this._showError(status.errorMessage || 'L\'import a échoué');
        }
    }

    _statusLabel(status) {
        const labels = {
            pending: 'En attente…',
            extracting: 'Extraction des archives…',
            processing: 'Import des médias…',
            completed: 'Import terminé',
            failed: 'Échec de l\'import',
        };
        return labels[status] || status;
    }

    _showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    _hideError() {
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
    }
}
