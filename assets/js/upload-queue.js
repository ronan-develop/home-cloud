/**
 * Upload queue — logique pure, sans dépendance DOM.
 * 
 * Gère une file d'attente d'uploads avec concurrence limitée.
 * Injecte la fonction d'upload (DIP) pour zéro couplage.
 * 
 * États d'un item : PENDING → UPLOADING → DONE
 *                             ↘ ERROR → (retry) → PENDING
 *                  CANCELLED ←──────────┘
 * 
 * @module uploadQueue
 */

const STATES = {
  PENDING: 'pending',
  UPLOADING: 'uploading',
  DONE: 'done',
  ERROR: 'error',
  CANCELLED: 'cancelled',
};

/**
 * Crée une queue d'upload.
 *
 * @param {Object} options
 * @param {Function} [options.uploadFn] - (file, metadata, onProgress?) => Promise<response>
 * @param {number} [options.maxConcurrent=3] - Uploads simultanés max
 * @param {Function} [options.onProgress] - (file, {loaded, total, percent}) => void
 * @param {Function} [options.onComplete] - (file, response) => void
 * @param {Function} [options.onError] - (file, error) => void
 * @param {Function} [options.onQueueDone] - () => void
 * @returns {Object} Queue API
 */
export function createUploadQueue(options) {
  const {
    uploadFn = null,
    maxConcurrent = 3,
    onProgress = () => {},
    onComplete = () => {},
    onError = () => {},
    onQueueDone = () => {},
  } = options;

  if (uploadFn !== null && typeof uploadFn !== 'function') {
    throw new Error('uploadFn must be a function or null');
  }

  if (maxConcurrent < 1) {
    throw new Error('maxConcurrent must be at least 1');
  }

  // État interne
  const items = new Map(); // file → {file, state, progress, error?, response?, abortController}
  let runningCount = 0;
  let _uploadFn = uploadFn;

  /**
   * Lance le traitement de la queue
   * @private
   */
  function processQueue() {
    while (runningCount < maxConcurrent) {
      const pendingItem = Array.from(items.values()).find(
        item => item.state === STATES.PENDING
      );

      if (!pendingItem) break;

      runningCount++;
      pendingItem.state = STATES.UPLOADING;

      // Créer AbortController pour cette tâche
      const abortController = new AbortController();
      pendingItem.abortController = abortController;

      // Lancer l'upload
      const progressHandler = (data) => {
        pendingItem.progress = data;
        onProgress(pendingItem.file, data);
      };

      _uploadFn(pendingItem.file, pendingItem.metadata, progressHandler)
        .then(response => {
          pendingItem.state = STATES.DONE;
          pendingItem.response = response;
          onComplete(pendingItem.file, response);
        })
        .catch(error => {
          if (pendingItem.state !== STATES.CANCELLED) {
            pendingItem.state = STATES.ERROR;
            pendingItem.error = error;
            onError(pendingItem.file, error);
          }
        })
        .finally(() => {
          runningCount--;
          processQueue();

          // Vérifier si la queue est vide
          const allDone = Array.from(items.values()).every(
            item => [STATES.DONE, STATES.ERROR, STATES.CANCELLED].includes(item.state)
          );
          if (allDone && runningCount === 0) {
            onQueueDone();
          }
        });
    }
  }

  // API publique
  return {
    /**
     * Ajoute des fichiers à la queue
     * @param {File[]} files
     * @param {Object} metadata
     */
    enqueue(files, metadata = {}) {
      files.forEach(file => {
        items.set(file, {
          file,
          state: STATES.PENDING,
          metadata,
          progress: { loaded: 0, total: file.size, percent: 0 },
          error: null,
          response: null,
          abortController: null,
        });
      });

      // Only process if uploadFn is available
      if (_uploadFn) {
        processQueue();
      }
    },

    /**
     * Annule un fichier spécifique
     * @param {File} file
     */
    cancel(file) {
      const item = items.get(file);
      if (item && !item.abortController?.signal.aborted) {
        item.state = STATES.CANCELLED;
        item.abortController?.abort();
      }
    },

    /**
     * Annule tous les fichiers
     */
    cancelAll() {
      items.forEach(item => {
        if (![STATES.DONE, STATES.CANCELLED].includes(item.state)) {
          item.state = STATES.CANCELLED;
          item.abortController?.abort();
        }
      });
    },

    /**
     * Relance un fichier en erreur
     * @param {File} file
     */
    retry(file) {
      const item = items.get(file);
      if (item && item.state === STATES.ERROR) {
        item.state = STATES.PENDING;
        item.error = null;
        item.response = null;
        item.progress = { loaded: 0, total: file.size, percent: 0 };
        processQueue();
      }
    },

    /**
     * Relance tous les fichiers en erreur
     */
    retryAll() {
      items.forEach(item => {
        if (item.state === STATES.ERROR) {
          item.state = STATES.PENDING;
          item.error = null;
          item.response = null;
          item.progress = { loaded: 0, total: item.file.size, percent: 0 };
        }
      });
      processQueue();
    },

    /**
     * Retourne les statistiques de la queue
     * @returns {Object} {total, pending, uploading, done, error, cancelled}
     */
    getStats() {
      const itemArray = Array.from(items.values());
      return {
        total: itemArray.length,
        pending: itemArray.filter(i => i.state === STATES.PENDING).length,
        uploading: itemArray.filter(i => i.state === STATES.UPLOADING).length,
        done: itemArray.filter(i => i.state === STATES.DONE).length,
        error: itemArray.filter(i => i.state === STATES.ERROR).length,
        cancelled: itemArray.filter(i => i.state === STATES.CANCELLED).length,
        running: runningCount,
        maxConcurrent,
      };
    },

    /**
     * Retourne les items de la queue
     * @returns {Object[]}
     */
    getItems() {
      return Array.from(items.values()).map(item => ({
        file: item.file,
        state: item.state,
        progress: item.progress,
        error: item.error,
        response: item.response,
      }));
    },

    /**
     * Nettoie la queue
     */
    destroy() {
      items.forEach(item => {
        item.abortController?.abort();
      });
      items.clear();
      runningCount = 0;
    },

    /**
     * Définit l'uploadFn (utile si créé sans uploadFn initial)
     * @param {Function} fn - (file, metadata, onProgress?) => Promise<response>
     */
    setUploadFn(fn) {
      if (typeof fn !== 'function') {
        throw new Error('uploadFn must be a function');
      }
      _uploadFn = fn;
    },

    /**
     * Démarre le traitement de la queue
     * @param {Function} [overrideUploadFn] - Optionnel, remplace temporairement uploadFn
     * @returns {Promise<void>} Résolue quand toute la queue est traitée
     */
    async process(overrideUploadFn) {
      if (overrideUploadFn) {
        if (typeof overrideUploadFn !== 'function') {
          throw new Error('overrideUploadFn must be a function');
        }
        _uploadFn = overrideUploadFn;
      }
      if (!_uploadFn) {
        throw new Error('No uploadFn set. Call setUploadFn() or pass it to process()');
      }
      
      // Lancer le traitement
      processQueue();
      
      // Attendre que tous les items soient traités
      return new Promise((resolve) => {
        const checkDone = () => {
          const allDone = Array.from(items.values()).every(i => 
            [STATES.DONE, STATES.ERROR, STATES.CANCELLED].includes(i.state)
          );
          if (allDone && runningCount === 0) {
            resolve();
          } else {
            setTimeout(checkDone, 50);
          }
        };
        checkDone();
      });
    },
  };
}
