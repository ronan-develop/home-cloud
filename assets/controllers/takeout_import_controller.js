import { Controller } from '@hotwired/stimulus';
import { apiFetch } from '../js/api.js';
import { createBatchPoller } from '../js/upload-batch.js';

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
    static targets = ['input', 'dropzone', 'form', 'fileList', 'progress', 'bar', 'status', 'counts', 'error', 'submit'];

    connect() {
        this._poller = null;
    }

    disconnect() {
        this._poller?.stop();
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

        try {
            const importId = await this._createImport();
            for (const file of files) {
                await this._uploadFile(importId, file);
            }
            await this._startImport(importId);

            this.formTarget.hidden = true;
            this.progressTarget.hidden = false;
            this._startPolling(importId);
        } catch (err) {
            this._showError(err.message || 'Erreur lors de l\'envoi');
            this.submitTarget.disabled = false;
        }
    }

    async _createImport() {
        const res = await this._authenticatedFetch(IMPORTS_ROUTE, { method: 'POST' });
        if (!res.ok) {
            throw new Error(`Échec de la création de l'import (${res.status})`);
        }
        const data = await res.json();
        return data.id;
    }

    async _uploadFile(importId, file) {
        const formData = new FormData();
        formData.append('file', file);

        const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/${importId}/files`, {
            method: 'POST',
            body: formData,
        });
        if (!res.ok) {
            throw new Error(`Échec de l'envoi de ${file.name} (${res.status})`);
        }
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
