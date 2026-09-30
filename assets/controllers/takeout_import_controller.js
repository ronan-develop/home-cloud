import { Controller } from '@hotwired/stimulus';
import { apiFetch } from '../js/api.js';
import { createBatchPoller } from '../js/upload-batch.js';
import { uploadFileInChunks, hashChunk, CHUNK_SIZE_BYTES } from '../js/chunked-upload.js';

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
        'fileProgressWrapper', 'fileBar', 'fileStatus', 'patienceMessage', 'pendingList', 'safeToCloseMessage',
        'zipsProgress',
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

    // #545 (suite) : rafraîchit la liste des imports actifs pour que les
    // barres par ZIP avancent sans devoir recharger la page — la barre de
    // l'import "activement ciblé" (juste après Reprendre/Démarrer) a déjà
    // son propre poller (createBatchPoller), mais la LISTE elle-même
    // (plusieurs imports à statuts différents, pas de notion de "un seul
    // batch termine") n'était rafraîchie qu'au chargement initial de la
    // page. Intervalle volontairement plus long que le poller d'upload
    // (moins critique de rater quelques secondes ici), pour rester léger
    // sur le mutualisé o2switch.
    static PENDING_LIST_REFRESH_MS = 10000;

    connect() {
        this._poller = null;
        this._patienceInterval = null;
        this._pendingListRefreshInterval = null;
        // Import ciblé explicitement via le bouton "Reprendre" de la liste
        // des imports en attente — prioritaire sur la déduction automatique
        // "le plus récent" (#491) dès que l'utilisateur choisit lui-même
        // quel import reprendre (nécessaire dès que plusieurs coexistent).
        this._targetImportId = null;
        this._renderPendingList();
        this._pendingListRefreshInterval = setInterval(
            () => this._renderPendingList(),
            this.constructor.PENDING_LIST_REFRESH_MS,
        );
    }

    disconnect() {
        this._poller?.stop();
        this._stopPatienceMessages();
        clearInterval(this._pendingListRefreshInterval);
    }

    triggerFilePicker() {
        this.inputTarget.click();
    }

    // #481 : affiche tous les imports en attente de l'utilisateur, avec
    // reprise/abandon explicites — sans ça, rien sur la page n'indique
    // qu'un upload interrompu peut être repris avant que l'utilisateur ne
    // resélectionne ses fichiers au hasard.
    async _renderPendingList() {
        const imports = await this._fetchPendingList();
        this.pendingListTarget.innerHTML = '';

        imports.forEach((pendingImport) => {
            this.pendingListTarget.appendChild(this._buildPendingListItem(pendingImport));
        });
    }

    async _fetchPendingList() {
        try {
            const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/pending-list`, { method: 'GET' });
            return res.ok ? await res.json() : [];
        } catch {
            // Enrichissement non bloquant de la page : une erreur réseau ici
            // ne doit jamais empêcher l'utilisateur de démarrer un nouvel
            // import normalement.
            return [];
        }
    }

    // #545 : le bouton "Reprendre" (rouvre le sélecteur de fichiers) n'a de
    // sens que pour un import "pending" (upload pas terminé) — pour
    // scheduled/extracting/processing, l'upload est déjà fini, il n'y a rien
    // à resélectionner. Sans cette distinction, l'utilisateur pouvait croire
    // qu'il fallait relancer un upload alors qu'un traitement tournait déjà
    // (doublon constaté en conditions réelles le 2026-09-30).
    _buildPendingListItem(pendingImport) {
        const li = document.createElement('li');
        li.className = 'settings-card flex flex-col gap-3';

        const header = document.createElement('div');
        header.className = 'flex items-center justify-between gap-3';

        const info = document.createElement('span');
        const createdAt = new Date(pendingImport.createdAt).toLocaleString();
        info.textContent = `Import du ${createdAt} — ${this._activeImportStatusLabel(pendingImport)}`;
        header.appendChild(info);

        const actions = document.createElement('span');
        actions.className = 'flex gap-2';

        if (pendingImport.status === 'pending') {
            const resumeButton = document.createElement('button');
            resumeButton.type = 'button';
            resumeButton.className = 'btn btn-primary';
            resumeButton.textContent = 'Reprendre';
            resumeButton.addEventListener('click', () => this._resumePendingImport(pendingImport.id));
            actions.appendChild(resumeButton);
        }

        const abandonButton = document.createElement('button');
        abandonButton.type = 'button';
        abandonButton.className = 'btn';
        abandonButton.textContent = 'Abandonner';
        abandonButton.addEventListener('click', () => this._abandonPendingImport(pendingImport.id, li));
        actions.appendChild(abandonButton);

        header.appendChild(actions);
        li.appendChild(header);

        // Constaté en conditions réelles le 2026-09-30 : au rechargement de
        // la page pendant qu'un import tourne déjà côté serveur, rien
        // n'indiquait qu'il s'agit d'un traitement serveur (pas d'un upload
        // en attente de l'utilisateur) et que la page peut être quittée sans
        // risque — seul le bloc "progress" affiché juste après avoir
        // soi-même démarré un upload avait ce message (#482).
        if (['scheduled', 'extracting', 'processing'].includes(pendingImport.status)) {
            const safeToCloseMessage = document.createElement('p');
            safeToCloseMessage.className = 'text-xs';
            safeToCloseMessage.style.color = 'var(--hc-text-secondary, var(--hc-text))';
            safeToCloseMessage.textContent = 'Le serveur travaille : vous pouvez quitter cette page, l\'import continue en arrière-plan.';
            li.appendChild(safeToCloseMessage);
        }

        if (pendingImport.status === 'extracting' && pendingImport.zipsProgress?.length) {
            const zipsList = document.createElement('ul');
            zipsList.className = 'flex flex-col gap-2';
            li.appendChild(zipsList);
            this._renderZipsProgress(pendingImport.zipsProgress, zipsList);
        }

        return li;
    }

    _activeImportStatusLabel(pendingImport) {
        switch (pendingImport.status) {
            case 'pending':
                return `${pendingImport.filesUploadedCount} fichier(s) déjà reçu(s)`;
            case 'scheduled':
                return 'en attente d\'un créneau serveur calme…';
            case 'extracting':
                return pendingImport.totalZipCount
                    ? `extraction en cours (${pendingImport.extractedZipCount} / ${pendingImport.totalZipCount} archives)`
                    : 'extraction en cours…';
            case 'processing':
                return 'traitement des médias en cours…';
            default:
                return pendingImport.status;
        }
    }

    _resumePendingImport(importId) {
        this._targetImportId = importId;
        this.triggerFilePicker();
    }

    async _abandonPendingImport(importId, listItemElement) {
        const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/${importId}`, { method: 'DELETE' });
        if (!res.ok) {
            this._showError('Échec de l\'abandon de l\'import');
            return;
        }
        listItemElement.remove();
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

        // #481 : reprise d'un import interrompu par fermeture d'onglet —
        // avant de créer un nouvel import, chercher si un import "pending"
        // existe déjà pour l'utilisateur, et pour chaque fichier sélectionné,
        // si un envoi partiel correspondant (même nom, hash du premier chunk
        // identique) est déjà présent côté serveur. Sans ça, chaque nouvelle
        // tentative recrée un import et laisse les chunks déjà envoyés
        // orphelins côté serveur (nettoyage SSH manuel nécessaire, vécu
        // deux fois le 2026-09-27).
        const resumePlan = await this._resolveResumePlan(files);

        // Zone de progression affichée dès le début de l'upload (#477) —
        // avec des ZIP Takeout de plusieurs GB découpés en tranches (#466),
        // l'envoi seul peut durer plusieurs minutes ; un bouton simplement
        // grisé sans aucun autre retour ressemblait à un gel de l'interface
        // (constaté en conditions réelles avec 11 fichiers). Deux barres
        // distinctes : la globale suit les fichiers déjà envoyés en entier,
        // la seconde suit les chunks du fichier en cours.
        //
        // #507 : à la reprise, les fichiers déjà entièrement envoyés avant
        // la coupure ne doivent pas faire repartir la barre de 0 — leur
        // complétude réelle (connue côté serveur via /files/status) sert de
        // valeur de départ, plutôt que d'attendre qu'ils soient retraités un
        // par un dans la boucle ci-dessous pour "rattraper" leur progression.
        this._showUploadProgress(files.length, resumePlan.alreadyCompleteCount);

        try {
            const importId = resumePlan.importId ?? await this._createImport();
            let uploadedCount = resumePlan.alreadyCompleteCount;
            for (const file of files) {
                if (resumePlan.completeFilenames.has(file.name)) {
                    continue;
                }
                const resumeFromChunkIndex = resumePlan.resumeFromChunkIndexByFilename.get(file.name) ?? 0;
                await this._uploadFile(importId, file, (meta) => {
                    this._renderFileProgress(file.name, meta.chunkIndex + 1, meta.totalChunks);
                }, resumeFromChunkIndex);
                uploadedCount += 1;
                this._renderGlobalProgress(uploadedCount, files.length);
            }
            this.fileProgressWrapperTarget.hidden = true;
            await this._startImport(importId);

            this._stopPatienceMessages();
            // #482 : à partir d'ici, le traitement est entièrement
            // asynchrone côté serveur (Messenger) — contrairement à la
            // phase d'upload qui vient de se terminer (#481, à ne pas
            // interrompre), fermer l'onglet est désormais sans risque.
            this.safeToCloseMessageTarget.hidden = false;
            this._startPolling(importId);
        } catch (err) {
            this._hideUploadProgress();
            this._showError(this._describeUploadError(err));
            this.submitTarget.disabled = false;
        }
    }

    // "Failed to fetch" est le message brut du navigateur pour tout échec
    // réseau générique (coupure, veille prolongée du PC, DNS...) — trop
    // technique et anxiogène tel quel. La progression déjà envoyée reste
    // acquise côté serveur (#481) : le rassurer et l'inviter à relancer,
    // plutôt qu'un message qui laisse croire à une perte de données.
    _describeUploadError(err) {
        if (err instanceof TypeError && /fetch/i.test(err.message)) {
            return 'Connexion interrompue (mise en veille, coupure réseau...). '
                + 'Pas d\'inquiétude : ce qui a déjà été envoyé est conservé — '
                + 'sélectionnez à nouveau les mêmes fichiers et relancez, l\'envoi reprendra là où il s\'est arrêté.';
        }
        return err.message || 'Erreur lors de l\'envoi';
    }

    /**
     * @returns {Promise<{ importId: ?string, resumeFromChunkIndexByFilename: Map<string, number>, completeFilenames: Set<string>, alreadyCompleteCount: number }>}
     */
    async _resolveResumePlan(files) {
        // Import choisi explicitement via le bouton "Reprendre" de la liste
        // (#481) : prioritaire sur la déduction automatique du plus récent,
        // seul moyen non ambigu de savoir LEQUEL reprendre dès que plusieurs
        // imports pending coexistent.
        const targetImportId = this._targetImportId;
        this._targetImportId = null;
        const pendingImportId = targetImportId ?? (await this._findPendingImport())?.id ?? null;
        if (pendingImportId === null) {
            return {
                importId: null,
                resumeFromChunkIndexByFilename: new Map(),
                completeFilenames: new Set(),
                alreadyCompleteCount: 0,
            };
        }

        const uploadedFiles = await this._fetchFilesStatus(pendingImportId);
        const uploadedFilesByName = new Map(uploadedFiles.map((f) => [f.filename, f]));

        const resumeFromChunkIndexByFilename = new Map();
        const completeFilenames = new Set();
        for (const file of files) {
            const uploaded = uploadedFilesByName.get(file.name);
            if (uploaded === undefined) {
                continue;
            }
            // #507 : un fichier déjà entièrement reçu (plus de marqueur
            // .progress côté serveur) n'a plus besoin d'être renvoyé ni
            // retraité — sa complétude doit être reflétée immédiatement dans
            // la barre globale, pas seulement une fois "sauté" dans la boucle.
            if (uploaded.complete) {
                completeFilenames.add(file.name);
                continue;
            }
            // Vérifie que le premier chunk local correspond bien à ce que le
            // serveur a déjà écrit avant de reprendre — nom + taille de
            // fichier seuls ne suffisent pas à exclure une coïncidence
            // (fichier différent, même nom, taille proche).
            const firstChunk = file.slice(0, CHUNK_SIZE_BYTES);
            const localHash = await hashChunk(firstChunk);
            if (localHash === uploaded.hashOfFirstChunk) {
                resumeFromChunkIndexByFilename.set(file.name, uploaded.chunkIndex + 1);
            }
        }

        return {
            importId: pendingImportId,
            resumeFromChunkIndexByFilename,
            completeFilenames,
            alreadyCompleteCount: completeFilenames.size,
        };
    }

    async _findPendingImport() {
        const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/pending`, { method: 'GET' });
        if (!res.ok) {
            return null;
        }
        return res.json();
    }

    async _fetchFilesStatus(importId) {
        const res = await this._authenticatedFetch(`${IMPORTS_ROUTE}/${importId}/files/status`, { method: 'GET' });
        if (!res.ok) {
            return [];
        }
        return res.json();
    }

    _showUploadProgress(totalFiles, alreadyUploadedCount = 0) {
        this.formTarget.hidden = true;
        this.progressTarget.hidden = false;
        this.fileProgressWrapperTarget.hidden = true;
        this.safeToCloseMessageTarget.hidden = true;
        this._renderGlobalProgress(alreadyUploadedCount, totalFiles);
        this._startPatienceMessages();
    }

    _hideUploadProgress() {
        this.formTarget.hidden = false;
        this.progressTarget.hidden = true;
        this.fileProgressWrapperTarget.hidden = true;
        this.safeToCloseMessageTarget.hidden = true;
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

    async _uploadFile(importId, file, onChunkUploaded, resumeFromChunkIndex = 0) {
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
        }, { onChunkUploaded, resumeFromChunkIndex });
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

    // #545 : une barre par ZIP (nom + entrées extraites/totales), pour voir
    // lequel est en cours et son avancement — sans ça, un import à plusieurs
    // ZIP n'affichait qu'un pourcentage global agrégé, sans dire lequel
    // progresse (#546 introduit un batch limité par appel, donc un gros ZIP
    // peut rester "en cours" plusieurs minutes).
    _renderZipsProgress(zipsProgress, container = this.zipsProgressTarget) {
        container.innerHTML = '';

        zipsProgress.forEach((zip) => {
            const li = document.createElement('li');
            li.className = 'flex flex-col gap-1';

            const label = document.createElement('p');
            label.className = 'text-xs';
            label.style.color = 'var(--hc-text-secondary, var(--hc-text))';
            const percent = zip.totalEntries > 0 ? Math.round((zip.extractedEntries / zip.totalEntries) * 100) : 0;
            label.textContent = zip.isComplete
                ? `${zip.name} — terminé`
                : `${zip.name} — ${zip.extractedEntries} / ${zip.totalEntries} fichiers (${percent}%)`;
            li.appendChild(label);

            const track = document.createElement('div');
            track.className = 'takeout-progress-track';
            track.style.cssText = 'height: 4px; border-radius: 2px; overflow: hidden; background: var(--hc-border)';

            const bar = document.createElement('div');
            bar.className = 'takeout-progress-bar';
            bar.style.cssText = `height: 100%; width: ${percent}%; background: var(--hc-accent); opacity: ${zip.isComplete ? '0.6' : '1'}; transition: width 0.3s ease`;
            track.appendChild(bar);
            li.appendChild(track);

            container.appendChild(li);
        });
    }

    _renderStatus(status) {
        // #545 : détail par ZIP affiché uniquement pendant l'extraction —
        // sans intérêt une fois passé à "processing" (tous les ZIP sont
        // alors forcément complets).
        if (status.status === 'extracting' && status.zipsProgress) {
            this._renderZipsProgress(status.zipsProgress);
        } else if (this.hasZipsProgressTarget) {
            this.zipsProgressTarget.innerHTML = '';
        }

        // #515 : pendant "extracting", la progression connue est
        // extractedZipCount/totalZipCount (totalMediaCount n'est connu
        // qu'après le parsing, une fois l'extraction terminée) — sans ça,
        // la barre restait indéterminée pendant toute cette phase, parfois
        // plusieurs minutes sur des ZIP volumineux.
        if (status.status === 'extracting' && status.totalZipCount) {
            this.barTarget.classList.remove('takeout-progress-bar--indeterminate');
            const zipPercent = Math.min(100, Math.round((status.extractedZipCount / status.totalZipCount) * 100));
            this.barTarget.style.width = `${zipPercent}%`;
            this.statusTarget.textContent = this._statusLabel(status.status);
            this.countsTarget.textContent = `${status.extractedZipCount} / ${status.totalZipCount} archives extraites`;

            return;
        }

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
            // #522/#524 : l'import ne démarre plus immédiatement en cas de
            // contention serveur — sans ce label, l'utilisateur verrait le
            // statut brut de l'API sans comprendre pourquoi rien ne bouge.
            scheduled: 'En attente d\'un créneau serveur calme…',
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
