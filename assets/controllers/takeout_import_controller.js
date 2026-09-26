import { Controller } from '@hotwired/stimulus';
import { apiFetch } from '../js/api.js';
import { createBatchPoller } from '../js/upload-batch.js';

const UPLOAD_ROUTE = '/api/v1/takeout-imports';

/**
 * Page d'import Google Photos Takeout (#327) : upload du/des ZIP puis
 * suivi de la progression par polling (barre + compteurs), en réutilisant
 * le même poller que le suivi de lot d'upload classique (upload-batch.js) —
 * même stratégie de backoff/timeout, pas de duplication de cette logique.
 */
export default class extends Controller {
    static targets = ['input', 'dropzone', 'form', 'progress', 'bar', 'status', 'counts', 'error', 'submit'];

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
    }

    async submit(event) {
        event.preventDefault();

        const files = Array.from(this.inputTarget.files || []);
        if (files.length === 0) {
            return;
        }

        this.submitTarget.disabled = true;
        this._hideError();

        const formData = new FormData();
        files.forEach((file) => formData.append('files[]', file));

        try {
            const token = await (window.HC?.getToken?.() || Promise.resolve(''));
            const res = await fetch(UPLOAD_ROUTE, {
                method: 'POST',
                headers: token ? { Authorization: `Bearer ${token}` } : {},
                body: formData,
            });

            if (!res.ok) {
                throw new Error(`Échec de l'envoi (${res.status})`);
            }

            const data = await res.json();
            this.formTarget.hidden = true;
            this.progressTarget.hidden = false;
            this._startPolling(data.id);
        } catch (err) {
            this._showError(err.message || 'Erreur lors de l\'envoi');
            this.submitTarget.disabled = false;
        }
    }

    _startPolling(importId) {
        this._poller = createBatchPoller({
            batchId: importId,
            fetchStatus: (id) => this._fetchImportStatus(id),
            onComplete: (status) => this._renderStatus(status),
            onError: () => this._showError('Impossible de récupérer la progression'),
        });

        // Rendu immédiat (pending) avant le premier tick du poller, pour ne
        // pas laisser la barre vide pendant les premières secondes.
        this._fetchImportStatus(importId).then((status) => this._renderStatus(status));

        this._poller.start();
    }

    async _fetchImportStatus(importId) {
        const res = await apiFetch(`${UPLOAD_ROUTE}/${importId}`, { method: 'GET' });
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
