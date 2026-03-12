# Phase 5 — Multi-Upload Frontend (Liquid Glass)

> **Objectif** : Passer d'un upload mono-fichier à un multi-upload concurrent avec file d'attente, progression par fichier et UI Liquid Glass.

> **Principes** : SOLID · KISS · DRY · Dépendance aux interfaces (callbacks/events, pas de couplage concret)

---

## 📐 Architecture

### Modules concernés

```txt
assets/
├── js/
│   ├── api.js                    # ✅ Existant — wrapper fetch (token injection)
│   ├── modal.js                  # ✅ Existant — open/close par ID
│   ├── toast.js                  # ✅ Existant — notifications
│   ├── upload-queue.js           # 🆕 File d'attente (logique pure, zéro DOM)
│   └── upload-modal.js           # 🆕 Modal multi-upload (UI + orchestration)
├── controllers/
│   └── file_upload_controller.js # ♻️ Adapter — multi-fichier + dispatch events
└── styles/
    └── upload.css                # 🆕 Styles Liquid Glass upload
```

### Principes SOLID appliqués

| Principe | Application                                                                                                                                                               |
|----------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **SRP**  | `upload-queue.js` = logique queue pure. `upload-modal.js` = UI. `file_upload_controller.js` = drag&drop/input. Chacun une seule raison de changer.                        |
| **OCP**  | La queue est extensible via callbacks (`onProgress`, `onComplete`, `onError`) sans modifier son code.                                                                     |
| **ISP**  | Interfaces minimales : la queue n'a besoin que d'une fonction `uploadFn(file, metadata) → Promise`.                                                                       |
| **DIP**  | La queue ne connaît pas `apiFetch` — elle reçoit une fonction d'upload en paramètre (injection). La modal ne connaît pas la queue directement, elle passe par des events. |

### Flux de données

```txt
[Stimulus Controller]  →  dispatch 'hc:files-selected'  →  [upload-modal.js]
                                                                   ↓
                                                           ouvre modal Liquid Glass
                                                           user choisit dossier
                                                           user clique "Uploader"
                                                                   ↓
                                                            [upload-queue.js]
                                                           enqueue(files, metadata)
                                                                   ↓
                                                           max N concurrent uploads
                                                           callbacks: onProgress, onComplete, onError
                                                                   ↓
                                                            [upload-modal.js]
                                                           met à jour UI par fichier
                                                           toast succès/erreur
```

---

## 📦 Spec : `upload-queue.js`

Module de file d'attente **sans dépendance DOM**. Logique pure, testable unitairement.

### Interface publique

```javascript
/**
 * Crée une queue d'upload.
 *
 * @param {Object} options
 * @param {function(File, Object): Promise<Object>} options.uploadFn
 *        Fonction d'upload injectée (DIP). Reçoit (file, metadata), retourne Promise<response>.
 *        Signature volontairement abstraite : la queue ne sait pas si c'est fetch, XHR, ou un mock.
 * @param {number}   [options.maxConcurrent=3] — uploads simultanés max
 * @param {function} [options.onProgress]  — (file, {loaded, total, percent}) => void
 * @param {function} [options.onComplete]  — (file, response) => void
 * @param {function} [options.onError]     — (file, error) => void
 * @param {function} [options.onQueueDone] — () => void — quand toute la queue est finie
 */
export function createUploadQueue(options) {
    // Retourne un objet avec les méthodes :
    return {
        enqueue(files, metadata),  // File[] + {folderId?, newFolderName?, ownerId}
        cancel(file),              // Annule un upload (si en cours, abort le fetch)
        cancelAll(),               // Annule tout
        retry(file),               // Relance un fichier en erreur
        retryAll(),                // Relance tous les fichiers en erreur
        getStats(),                // {total, pending, uploading, done, error}
        getItems(),                // [{file, state, progress, error?, response?}, ...]
        destroy(),                 // Cleanup complet
    };
}
```

### États d'un item

```txt
PENDING → UPLOADING → DONE
                    ↘ ERROR → (retry) → PENDING
         CANCELLED ←──────────┘
```

### Règles métier

- **Concurrence** : max `maxConcurrent` uploads simultanés (défaut 3)
- **FIFO** : les fichiers sont traités dans l'ordre d'ajout
- **Annulation** : utilise `AbortController` par upload. `cancel()` abort le fetch en cours
- **Retry** : remet l'item en `PENDING`, recrée un `AbortController`, relance `processQueue()`
- **Progression** : via XHR `upload.onprogress` (fetch ne supporte pas le progress upload nativement → on utilise XHR wrappé dans une Promise)
- **DRY** : pas de duplication de la logique HTTP — la `uploadFn` est injectée

### uploadFn par défaut (XHR avec progress)

```javascript
// Dans upload-modal.js (pas dans la queue — SRP)
function createUploadFn(token, apiUrl) {
    return (file, metadata) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const fd = new FormData();
        fd.append('file', file);
        Object.entries(metadata).forEach(([k, v]) => v && fd.append(k, v));

        xhr.open('POST', apiUrl);
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);

        // L'item.abortController est géré par la queue
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                // La queue appelle onProgress via son propre mécanisme
                file.__xhrProgress?.({ loaded: e.loaded, total: e.total, percent: Math.round(e.loaded / e.total * 100) });
            }
        };
        xhr.onload = () => xhr.status === 201 ? resolve(JSON.parse(xhr.responseText)) : reject(new Error(`HTTP ${xhr.status}`));
        xhr.onerror = () => reject(new Error('Erreur réseau'));
        xhr.ontimeout = () => reject(new Error('Timeout'));

        // Permet à la queue d'abort
        file.__xhr = xhr;
        xhr.send(fd);
    });
}
```

> **Note** : On utilise XHR plutôt que `fetch` car `fetch` ne supporte pas `upload.onprogress`. C'est le seul endroit du projet qui utilise XHR (déjà le cas dans le code inline actuel).

---

## 📦 Spec : `upload-modal.js`

Module d'orchestration UI. Responsabilité unique : **piloter le DOM du modal et la queue**.

### Rôle

1. Écoute l'event `hc:files-selected` (dispatché par Stimulus)
2. Ouvre la modal → affiche la liste de fichiers sélectionnés
3. Gère la sélection de dossier destination (existant ou nouveau)
4. Au clic "Uploader" → crée la queue, enqueue les fichiers
5. Met à jour la progression par fichier (card Liquid Glass par fichier)
6. Affiche toast succès/erreur
7. Recharge la page quand tout est terminé

### Events écoutés

| Event | Source | Payload |
|-------|--------|---------|
| `hc:files-selected` | Stimulus controller | `{ detail: { files: File[] } }` |

### DOM généré dynamiquement

Chaque fichier est rendu dans une **mini-card Liquid Glass** dans la modal :

```html
<div class="upload-item" data-filename="photo.jpg">
    <div class="upload-item__info">
        <span class="upload-item__name">📎 photo.jpg</span>
        <span class="upload-item__size">2.4 MB</span>
        <span class="upload-item__status">En attente</span>
    </div>
    <div class="upload-item__progress">
        <div class="upload-item__bar" style="width: 0%"></div>
    </div>
    <button class="upload-item__cancel" title="Annuler">✕</button>
</div>
```

### Méthodes internes (non exportées)

- `renderFileList(files)` — génère les cards
- `updateFileProgress(file, progress)` — met à jour la barre
- `updateFileStatus(file, state)` — change icône/texte
- `formatSize(bytes)` — affiche KB/MB/GB

---

## 📦 Spec : `file_upload_controller.js` (mise à jour)

### Changements

| Avant                                | Après                                               |
|--------------------------------------|-----------------------------------------------------|
| `files[0]` (mono-fichier)            | `files` (tous les fichiers)                         |
| `input.files = dt.files` (1 fichier) | `input.multiple = true` + tous les fichiers du drop |
| Dispatch interne                     | Dispatch `hc:files-selected` avec `File[]`          |

### Attributs Stimulus

```html
<div data-controller="file-upload"
     data-file-upload-multiple-value="true"
     data-file-upload-folder-id-value="{{ currentFolder.id }}">
```

### Nouveau comportement

```javascript
// Au lieu de prendre files[0], prendre tous les fichiers
_handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.element.classList.remove('drop-active');

    const files = Array.from(e.dataTransfer?.files || []);
    if (files.length === 0) return;

    this._dispatchFiles(files);
}

fileSelected() {
    const files = Array.from(this.inputTarget.files);
    if (files.length > 0) this._dispatchFiles(files);
}

_dispatchFiles(files) {
    document.dispatchEvent(new CustomEvent('hc:files-selected', {
        detail: { files }
    }));
}
```

---

## 🎨 Design : Liquid Glass Upload

### Modal d'upload (refonte)

La modal actuelle (fond blanc, style utilitaire) est remplacée par une carte Liquid Glass.

```txt
┌─────────────────────────────────────────────┐
│  ╭─ LG Card ─────────────────────────────╮  │ ← backdrop-blur + overlay noir
│  │  lg-blur + lg-tint + lg-shine         │  │
│  │                                       │  │
│  │  📤 Importer 3 fichiers               │  │ ← lg-title
│  │                                       │  │
│  │  ┌─ Destination ─────────────────┐    │  │
│  │  │  🏠 Mes fichiers (racine) ✓   │    │  │ ← folder-opt active
│  │  │  📁 Photos                    │    │  │
│  │  │  📁 Documents                 │    │  │
│  │  │  [Ou créer un dossier…    ]   │    │  │ ← input lg-input
│  │  └───────────────────────────────┘    │  │
│  │                                       │  │
│  │  ┌─ Fichiers ────────────────────┐    │  │
│  │  │  📎 photo1.jpg    2.4 MB      │    │  │ ← upload-item
│  │  │  ████████████░░░░  65%   [✕]  │    │  │ ← progress bar
│  │  │  📎 photo2.jpg    1.1 MB      │    │  │
│  │  │  ██████████████████ ✅   [✕]  │    │  │ ← done
│  │  │  📎 doc.pdf       340 KB      │    │  │
│  │  │  ░░░░░░░░░░░░░░░ En attente   │    │  │ ← pending
│  │  └───────────────────────────────┘    │  │
│  │                                       │  │
│  │  [Annuler]              [Uploader ⬆️] │  │ ← lg-btn
│  ╰───────────────────────────────────────╯  │
└─────────────────────────────────────────────┘
```

### Classes CSS (`upload.css`)

```css
/* === Modal overlay === */
.upload-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(4px);
}

/* === Carte Liquid Glass (réutilise le pattern lg-*) === */
.upload-modal-card {
    position: relative;
    width: 100%;
    max-width: 480px;
    margin: 1rem;
    border-radius: 1.6rem;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.22);
    background: rgba(255, 255, 255, 0.08);
}

/* Couches LG (réutilise lg-blur, lg-tint, lg-shine, lg-topline, lg-content) */

/* === Liste de fichiers === */
.upload-file-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 280px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

/* === Item fichier === */
.upload-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.6rem 0.75rem;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
}

.upload-item__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.upload-item__name {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}

.upload-item__size {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.45);
    flex-shrink: 0;
}

.upload-item__status {
    font-size: 0.72rem;
    font-weight: 600;
    flex-shrink: 0;
}

.upload-item__status--pending   { color: rgba(255, 255, 255, 0.4); }
.upload-item__status--uploading { color: #60a5fa; }
.upload-item__status--done      { color: #34d399; }
.upload-item__status--error     { color: #f87171; }

/* Barre de progression — fine, arrondie, effet glass */
.upload-item__progress {
    height: 3px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
    overflow: hidden;
}

.upload-item__bar {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    border-radius: 2px;
    transition: width 0.3s ease;
    box-shadow: 0 0 6px rgba(59, 130, 246, 0.4);
}

.upload-item--done .upload-item__bar {
    background: linear-gradient(90deg, #10b981, #34d399);
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
}

.upload-item--error .upload-item__bar {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

/* Bouton annuler */
.upload-item__cancel {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.15rem 0.35rem;
    border-radius: 0.4rem;
    transition: all 0.15s;
}

.upload-item__cancel:hover {
    color: #f87171;
    background: rgba(248, 113, 113, 0.12);
}

/* === Section dossiers === */
.upload-folders {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-height: 160px;
    overflow-y: auto;
}

.upload-folder-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    border-radius: 0.65rem;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.6);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    text-align: left;
    width: 100%;
}

.upload-folder-btn:hover {
    background: rgba(255, 255, 255, 0.06);
}

.upload-folder-btn--active {
    color: #93c5fd;
    background: rgba(59, 130, 246, 0.12);
    border-color: rgba(59, 130, 246, 0.2);
}

/* === Boutons action === */
.upload-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 0.75rem;
}

.upload-btn-cancel {
    padding: 0.5rem 1rem;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.5);
    background: transparent;
    border: none;
    border-radius: 0.65rem;
    cursor: pointer;
    transition: all 0.15s;
}

.upload-btn-cancel:hover {
    color: rgba(255, 255, 255, 0.8);
}

.upload-btn-submit {
    padding: 0.5rem 1.25rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none;
    border-radius: 0.65rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.upload-btn-submit:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.upload-btn-submit:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* === Compteur global === */
.upload-global-progress {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
    margin-top: 0.5rem;
}

/* === Responsive === */
@media (max-width: 480px) {
    .upload-modal-card {
        margin: 0.5rem;
        border-radius: 1.2rem;
    }
    .upload-file-list {
        max-height: 200px;
    }
}
```

### Intégration avec les classes LG existantes

La modal réutilise les couches `lg-blur`, `lg-tint`, `lg-shine`, `lg-topline`, `lg-content` de `liquid-glass.css` pour l'effet glass. La carte `.upload-modal-card` remplace `.lg-card` avec une `max-width` plus large (480px vs 430px) et un `border-radius` légèrement réduit.

Le SVG filter `#lg` (distorsion) est déjà présent dans le DOM via la page login — il faut s'assurer qu'il est aussi présent dans le layout principal, dans un `<svg>` caché :

```html
<svg class="hidden" aria-hidden="true">
    <defs>
        <filter id="lg">
            <feTurbulence type="fractalNoise" baseFrequency="0.55 0.65" numOctaves="2" seed="5" />
            <feDisplacementMap in="SourceGraphic" scale="6" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </defs>
</svg>
```

---

## 🧪 Tests (Jest, TDD)

### Structure des tests

```txt
assets/tests/
├── upload-queue.test.js         # 🆕 Tests unitaires queue (logique pure)
└── delete-folder-modal.test.js  # ✅ Existant
```

### Tests prioritaires : `upload-queue.test.js`

La queue est le seul module à tester unitairement (logique pure, zéro DOM). La modal est testée manuellement (interaction DOM complexe, pas de framework de rendu).

**Cas de test :**

| #  | Test                                                       | Assert                                             |
|----|------------------------------------------------------------|----------------------------------------------------|
| 1  | `enqueue()` ajoute les fichiers en état PENDING            | `getStats().pending === N`                         |
| 2  | Lance max `maxConcurrent` uploads simultanés               | `uploadFn` appelé `maxConcurrent` fois             |
| 3  | Quand un upload finit, le suivant démarre                  | `uploadFn` appelé une fois de plus                 |
| 4  | `onComplete` appelé avec `(file, response)`                | callback reçoit les bons args                      |
| 5  | `onError` appelé si `uploadFn` rejette                     | callback reçoit `(file, error)`                    |
| 6  | `onProgress` appelé si `uploadFn` appelle le hook progress | callback reçoit `(file, {loaded, total, percent})` |
| 7  | `cancel(file)` passe l'item en CANCELLED                   | `getItems()` montre state === 'cancelled'          |
| 8  | `cancelAll()` annule tout                                  | `getStats().cancelled === total`                   |
| 9  | `retry(file)` remet en PENDING et relance                  | `uploadFn` appelé à nouveau                        |
| 10 | `retryAll()` relance tous les items en erreur              | `uploadFn` appelé N fois                           |
| 11 | `onQueueDone` appelé quand tout est fini                   | callback appelé une fois                           |
| 12 | `destroy()` annule et nettoie                              | `getItems().length === 0`                          |
| 13 | Ne dépasse jamais `maxConcurrent`                          | compteur d'appels concurrent ≤ max                 |
| 14 | FIFO : les fichiers sont traités dans l'ordre              | ordre des appels à `uploadFn`                      |

---

## 📋 Plan d'implémentation (TDD)

### Phase 5a — `upload-queue.js` (logique pure)

1. **RED** : Écrire `assets/tests/upload-queue.test.js` (14 cas ci-dessus)
2. **GREEN** : Implémenter `assets/js/upload-queue.js` — faire passer tous les tests
3. **REFACTOR** : Nettoyer si nécessaire
4. **Commit** : `✅ test(UploadQueue): add 14 unit tests for upload queue` puis `✨ feat(UploadQueue): concurrent upload queue with cancel/retry`

### Phase 5b — `upload.css` (styles Liquid Glass)

1. Créer `assets/styles/upload.css` avec les classes ci-dessus
2. Importer dans `assets/styles/app.css`
3. Ajouter le SVG filter `#lg` dans `layout.html.twig`
4. **Commit** : `🎨 style(Upload): add Liquid Glass upload styles`

### Phase 5c — `file_upload_controller.js` (multi-fichier)

1. Modifier le controller Stimulus pour supporter `multiple`
2. Dispatcher `hc:files-selected` avec `File[]`
3. L'input HTML doit avoir `multiple` (modifier le template NewMenu)
4. **Commit** : `♻️ refactor(FileUploadController): support multi-file selection`

### Phase 5d — `upload-modal.js` (UI orchestration)

1. Créer `assets/js/upload-modal.js`
2. Écoute `hc:files-selected` → ouvre la modal LG → affiche la file list
3. Intègre `createUploadQueue()` avec `createUploadFn()`
4. Gère sélection dossier, new folder, progress, états, toast
5. Importer dans `assets/app.js`
6. **Commit** : `✨ feat(UploadModal): multi-upload modal with Liquid Glass UI`

### Phase 5e — Nettoyage `layout.html.twig`

1. Supprimer tout le JS inline de l'upload (submitUpload, selectUploadFolder, onNewFolderName)
2. Remplacer le HTML de `#upload-modal` par la structure LG
3. Garder le token bridge `window.HC` (utilisé par `api.js` et d'autres modules)
4. **Commit** : `♻️ refactor(Layout): extract inline upload JS to modules`

### Phase 5f — Tests manuels & QA

1. Upload mono-fichier via bouton "parcourir"
2. Upload multi-fichier (sélection 5+ fichiers)
3. Drag & drop multi-fichier
4. Annulation en cours d'upload
5. Retry après erreur
6. Vérifier concurrence (max 3 simultanés, les autres en attente)
7. Upload dans dossier existant
8. Upload avec nouveau dossier

---

## 📝 Ce qui ne change PAS

- **Backend** : `FileUploadController` + `CreateFileService` restent identiques — déjà prêts pour le multi-upload concurrent (stateless)
- **`api.js`** : pas de modification (le XHR est dans la `uploadFn`, `api.js` reste pour les autres appels)
- **`modal.js`** : la modal upload utilise son propre open/close (DOM direct), mais peut réutiliser le pattern
- **`toast.js`** : réutilisé tel quel pour les notifications

---

## ⚠️ Points d'attention

| Point               | Détail                                                                                                                               |
|---------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| **XHR vs fetch**    | On garde XHR pour le progress upload (fetch ne le supporte pas). C'est encapsulé dans `createUploadFn()`, isolé du reste.            |
| **Token**           | `window.HC.getToken()` est async avec cache 14min. L'appeler **une fois** avant de lancer la queue, pas à chaque fichier.            |
| **AbortController** | Chaque item de la queue a son propre controller. `cancel()` appelle `xhr.abort()` via une ref sur le fichier.                        |
| **Reload page**     | Quand `onQueueDone` est appelé, on `window.location.reload()` (comme le code actuel). Pas de SPA.                                    |
| **SVG filter**      | Le filtre `#lg` doit être dans le DOM du layout, pas seulement sur la page login. Vérifier qu'il est présent.                        |
| **Accessibilité**   | La modal doit trapper le focus, être fermable via Escape, et les progress bars doivent avoir `role="progressbar"` + `aria-valuenow`. |
