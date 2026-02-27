# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-02-27 (JWT refresh token live ✅ + CreateUserCommand — 61/61 tests)

---

## 🔴 AVERTISSEMENT CRITIQUE — CLÉ DE CHIFFREMENT

```
╔══════════════════════════════════════════════════════════════════════════════╗
║  ⚠️  NE JAMAIS RÉGÉNÉRER APP_ENCRYPTION_KEY SI DES FICHIERS EXISTENT       ║
║                                                                              ║
║  Tous les fichiers uploadés sont chiffrés avec cette clé.                   ║
║  Changer la clé = TOUS les fichiers deviennent ILLISIBLES et IRRÉCUPÉRABLES ║
║                                                                              ║
║  Procédure si changement de clé OBLIGATOIRE :                               ║
║    1. Déchiffrer TOUS les fichiers avec l'ancienne clé                       ║
║    2. Générer la nouvelle clé                                                ║
║    3. Rechiffrer TOUS les fichiers avec la nouvelle clé                      ║
║    4. Vérifier chaque fichier avant de supprimer l'ancienne clé              ║
╚══════════════════════════════════════════════════════════════════════════════╝
```

---

## ✅ Fait

| Date       | Tâche                                                                           |
|------------|---------------------------------------------------------------------------------|
| 2026-02-27 | Fresh install API Platform 4 + Symfony 8 vérifiée                               |
| 2026-02-27 | Analyse compatibilité o2switch (⚠️ MySQL à prévoir, PostgreSQL 9.2 trop ancien) |
| 2026-02-27 | Init git + conventions de commit HomeCloud                                      |
| 2026-02-27 | Fix `composer.lock` incomplet via `composer require api`                        |
| 2026-02-27 | Flex recipes appliquées (api_platform, doctrine, nelmio_cors, twig...)          |
| 2026-02-27 | Serveur dev fonctionnel — `/api` opérationnel                                   |
| 2026-02-27 | Migration DB : PostgreSQL → **MySQL/MariaDB 10.6** (compatibilité o2switch)     |
| 2026-02-27 | **User** — Entity + DTO + StateProvider + migration + tests fonctionnels (TDD RED→GREEN) ✅ |
| 2026-02-27 | **Folder** — Entity + DTO + StateProvider/Processor + migration + tests TDD ✅ |
| 2026-02-27 | Fix: `@method` PHPDoc sur repositories (Intelephense P1013)                      |
| 2026-02-27 | 📖 Documentation classes non-entité (rôle, choix, intention) — UserOutput, FolderOutput, UserProvider |
| 2026-02-27 | Setup PHPUnit 13 + symfony/test-pack — 3 tests / 9 assertions ✅                |
| 2026-02-27 | **File upload** — Entity + migration + StorageService + DefaultFolderService ✅ |
| 2026-02-27 | **File upload** — FileOutput DTO + FileProvider + FileProcessor ✅              |
| 2026-02-27 | **File upload** — FileUploadController (multipart/form-data) ✅                 |
| 2026-02-27 | **File upload** — FileDownloadController `GET /api/v1/files/{id}/download` ✅   |
| 2026-02-27 | **File upload** — DELETE supprime fichier physique + métadonnées ✅             |
| 2026-02-27 | **File upload** — Blocage exécutables (.exe, .sh, .bat, .ps1, .dmg…) ✅        |
| 2026-02-27 | **File upload** — Aucune restriction de taille (stockage illimité) ✅           |
| 2026-02-27 | 📖 Documentation PHPDoc — FileOutput, FileProvider, FileProcessor, StorageService, DefaultFolderService, FileUploadController, FileDownloadController, File entity |
| 2026-02-27 | 27/27 tests passing ✅ (User 3 + Folder 9 + File 15)                            |
| 2026-02-27 | Conventions de commit clarifiées dans copilot-instructions.md (emoji + scope explicite) |
| 2026-02-27 | Branches : `main` ← feat/user-entity mergé ; `feat/file-upload` en cours        |
| 2026-02-27 | `feat/file-upload` → mergé dans `main`, toutes branches nettoyées               |
| 2026-02-27 | **Media** — Entity + migration + Repository (`medias` table) ✅                 |
| 2026-02-27 | **MediaProcessMessage** — message Messenger pour traitement async ✅            |
| 2026-02-27 | **ExifService** — extraction EXIF (exif_read_data + GPS decimal) ✅             |
| 2026-02-27 | **ThumbnailService** — génération thumbnail GD 320px JPEG (graceful si absent) ✅ |
| 2026-02-27 | **MediaProcessHandler** — handler async idempotent (image/* + video/*) ✅       |
| 2026-02-27 | **MediaOutput + MediaProvider** — GET /api/v1/medias, GET /api/v1/medias/{id}, filtre ?type= ✅ |
| 2026-02-27 | **MediaThumbnailController** — GET /api/v1/medias/{id}/thumbnail ✅             |
| 2026-02-27 | Messenger configuré : doctrine transport (prod), in-memory (tests) ✅           |
| 2026-02-27 | 38/38 tests passing ✅ (User 3 + Folder 9 + File 15 + Media 8 + Handler 3)      |
| 2026-02-27 | 🔒 **Audit sécurité** — extensions PHP bloquées (.php, .phar, .phtml, .py, .rb, .asp…) ✅ |
| 2026-02-27 | 🔒 **Audit sécurité** — `HeaderUtils::makeDisposition()` RFC 6266 (remplace addslashes) ✅ |
| 2026-02-27 | 🔒 **Audit sécurité** — `realpath()` + vérification sortie du storageDir (path traversal) ✅ |
| 2026-02-27 | 🔧 **Bug** — suppression thumbnail disque lors du DELETE File (était orphelin) ✅   |
| 2026-02-27 | 42/42 tests passing ✅ (+ 3 sécurité + 1 thumbnail + fix setUp FK FolderTest/FileTest) |
| 2026-02-27 | 🔒 **fix/security-hardening** — 8 correctifs de sécurité (voir section 8 ci-dessous) ✅ |
| 2026-02-27 | 47/47 tests passing ✅ (+ nosniff, type filter, sanitize, folderName, security headers) |
| 2026-02-27 | 🔐 **feat/encryption-at-rest** — chiffrement XChaCha20-Poly1305 de tous les fichiers + thumbnails ✅ |
| 2026-02-27 | 🛡️ SVG, HTML, XML, JS, CSS acceptés à l'upload (neutralisés par chiffrement, binaire opaque sur disque) ✅ |
| 2026-02-27 | 50/50 tests passing ✅ (+ vérification chiffrement disque + SVG/HTML acceptés) |
| 2026-02-27 | 🔒 **fix/security-round2** — 4 correctifs : CSP header, cycle parent dossier, GD memory bomb, ownership cross-user ✅ |
| 2026-02-27 | 52/52 tests passing ✅ (+ CSP, folder cycle, GD bomb, IDOR ownership) |
| 2026-02-27 | ✨ **feat/sec2-pagination** — pagination `TraversablePaginator` sur UserProvider, FolderProvider, FileProvider (DB-level offset/limit) ✅ |
| 2026-02-27 | ✨ **feat/deploy-secrets-gen** — script `bin/generate-secrets.sh` (APP_ENCRYPTION_KEY + APP_SECRET → .env.local) ✅ |
| 2026-02-27 | ✨ **feat/jwt-auth** — Phase 4 authentification JWT stateless (Lexik, firewall, User entity, AuthenticatedApiTestCase) ✅ |
| 2026-02-27 | ♻️ **refactor/solid-interfaces** — extraction `EncryptionServiceInterface`, `StorageServiceInterface`, `DefaultFolderServiceInterface` (DIP) ✅ |
| 2026-02-27 | ♻️ **refactor/interfaces-folder** — déplacement des interfaces dans `src/Interface/` (namespace `App\Interface`) ✅ |
| 2026-02-27 | 57/57 tests passing ✅ |
| 2026-02-27 | ♻️ **Namespace fix** — mise à jour des `use` statements dans controllers + FileProcessor (`App\Interface`) ✅ |
| 2026-02-27 | 🛠️ **Remote cleanup** — force-push `main`, suppression 5 branches obsolètes (feat/Photo, feat/albums, fix/security-round2, refactor/album-controller, snapshot/avant-reset) ✅ |
| 2026-02-27 | ✨ **feat/jwt-refresh-token** — RefreshToken entity + migration + listener + controller `POST /api/v1/auth/token/refresh` (rotation, 7j TTL) ✅ |
| 2026-02-27 | 61/61 tests passing ✅ (+ 4 tests refresh token) |
| 2026-02-27 | ✨ **feat(CreateUserCommand)** — CLI `app:create-user <email> <password> [displayName]` pour créer un utilisateur en dev/prod ✅ |
| 2026-02-27 | 🔧 **fix(Version20260227201400)** — migration `refresh_tokens` corrigée : BINARY(16) au lieu de CHAR(36) (cohérence DBAL 4) ✅ |
| 2026-02-27 | 🏗️ **build(maker-bundle)** — `symfony/maker-bundle` installé en dev ✅ |
| 2026-02-27 | 🔥 **Live test validé** — login → refresh_token → rotation → route protégée, tout OK sur 127.0.0.1:8000 ✅ |
| 2026-02-27 | 🔧 **fix(security.yaml)** — `/api/docs` ajouté en `PUBLIC_ACCESS` (était bloqué par firewall JWT) ✅ |
| 2026-02-27 | 📖 **API Docs** — Swagger UI accessible à `https://127.0.0.1:8000/api/docs` (ou `/api/docs?ui=re_doc` pour ReDoc) · spec OpenAPI : `/api/docs.jsonopenapi` |

---

## 🚧 En cours

- Rien — `main` est propre, remote synchronisé (61/61 tests ✅)
- `CreateUserCommand` implémentée et testée en live
- Prochaine phase : **Phase 4** — Albums ou **Phase 5** — déploiement o2switch

---

## 📌 Backlog — Domaine : Stockage & Médias

### 🔵 Phase 1 — Fondations (User + Folder)

- [x] **User** — Entity + migration + ApiResource (`GET /api/v1/users/{id}`, `POST /api/v1/users`) ✅
- [x] **Folder** — Entity + migration + ApiResource (arborescence parent/enfants) ✅
  - `GET /api/v1/folders` (paginé)
  - `POST /api/v1/folders`
  - `GET /api/v1/folders/{id}`
  - `PATCH /api/v1/folders/{id}`
  - `DELETE /api/v1/folders/{id}`

### 🔵 Phase 2 — Fichiers ✅

- [x] **File** — Entity + migration + ApiResource (upload, lié à Folder + User)
  - `GET /api/v1/files` (filtrable par `?folderId=`)
  - `POST /api/v1/files` (multipart/form-data : file + ownerId + folderId? + newFolderName?)
  - `GET /api/v1/files/{id}`
  - `GET /api/v1/files/{id}/download` (stream binaire avec Content-Type)
  - `DELETE /api/v1/files/{id}` (supprime DB + fichier physique)
- [x] **StorageService** — stockage `var/storage/{year}/{month}/{uuid}.{ext}`
- [x] **DefaultFolderService** — résolution dossier : folderId > newFolderName > Uploads (lazy)
- [x] Blocage exécutables, pas de restriction de taille
- [x] `config/php.ini` — référence pour déploiement (`upload_max_filesize=10G`)

### 🔵 Phase 3 — Médias ✅

- [x] **Media** — Entity + migration + ApiResource (enrichit File : EXIF, thumbnail, type photo/vidéo)
  - `GET /api/v1/medias` (filtrable par `?type=`)
  - `GET /api/v1/medias/{id}`
  - `GET /api/v1/medias/{id}/thumbnail`
- [x] **MediaProcessMessage** — dispatch async après upload image/* ou video/*
- [x] **ExifService** — extraction EXIF (orientation, GPS, date, modèle caméra)
- [x] **ThumbnailService** — génération 320px JPEG (GD, graceful si absent)
- [x] **MediaProcessHandler** — création Media idempotente depuis File
- [x] Symfony Messenger configuré (doctrine prod, in-memory tests)

### 🔵 Phase 4 — Albums _(à venir)_

- [ ] **Album** — collection de Media, sans structure de dossier

### 🔵 Phase 5 — Domotique / Dashboard _(futur)_

- [ ] À définir

---

## 🏛️ Décisions d'architecture

### 1. Pourquoi des controllers Symfony pour certains endpoints ?

API Platform gère automatiquement les opérations CRUD standard (GET, POST JSON, PATCH, DELETE) via ses **StateProcessors** et **StateProviders**. Mais deux cas nécessitent un controller Symfony classique (`AbstractController`) :

#### `FileUploadController` — POST multipart/form-data

API Platform ne sait pas désérialiser un body `multipart/form-data` nativement. Son système de désérialisation attend du JSON ou du JSON-LD. Pour un upload binaire, il faut accéder directement à `$request->files` — ce qui n'est possible que dans un controller bas-niveau.

> **Règle** : `deserialize: false` sur l'opération + controller dédié = on court-circuite le pipeline API Platform et on gère la `Request` Symfony brute. Le controller DOIT retourner un objet `Response` (pas un DTO), sinon Symfony lève une exception.

#### `FileDownloadController` — GET stream binaire

Renvoyer un fichier binaire avec ses headers (`Content-Type`, `Content-Disposition`) ne rentre pas dans le modèle de sérialisation JSON d'API Platform. Il faut une `BinaryFileResponse` ou `Response` avec `file_get_contents()`.

> **⚠️ Gotcha tests** : `BinaryFileResponse` retourne un body vide dans le client PHPUnit (il ne lit pas le disque). Solution : `new Response(file_get_contents($path))` dans les tests ou vérifier uniquement le status HTTP.

#### `MediaThumbnailController` — GET /medias/{id}/thumbnail

Même raison que le download : réponse binaire (image JPEG). De plus, la route ne suit pas le pattern d'une ressource API Platform standard (pas de collection, ID composite dans l'URL).

**Résumé** : un controller Symfony est utilisé **uniquement** quand API Platform ne peut pas gérer nativement le format de la requête ou de la réponse. Tout le reste passe par les StateProviders/Processors.

---

### 2. Architecture en couches : DTOs, Providers, Processors

```
Requête HTTP
    │
    ▼
ApiResource (DTO — src/ApiResource/)
    │  Définit les opérations, la sérialisation, le provider/processor
    │
    ├─── Lecture  → StateProvider (src/State/) → Repository → DTO
    └─── Écriture → StateProcessor (src/State/) ou Controller → Entity → DB
```

**Pourquoi ne jamais exposer les entités Doctrine directement ?**

- Une entité peut changer de structure (refactoring DB) sans casser le contrat API
- On contrôle exactement quels champs sont exposés
- On évite les références circulaires de sérialisation (ex : User → Folder → User)
- Les DTOs sont `readonly` : impossible de les modifier par erreur

---

### 3. Relation File ↔ Media : OneToOne vs héritage

**Choix : OneToOne** (Media a une FK vers File, pas l'inverse).

- `File` reste **générique** : il ne sait pas s'il est un média. C'est voulu — un PDF, un CSV, etc. sont des Files sans Media.
- `Media` **enrichit optionnellement** un File avec EXIF, thumbnail, dimensions.
- Héritage Doctrine (STI/CTI) aurait compliqué les requêtes et couplé les deux concepts.
- La relation est nullable côté File : `$file->getMedia()` peut retourner `null`.

**Idempotence du handler** : avant de créer un Media, le handler vérifie `mediaRepository->findOneBy(['file' => $file])`. Si un Media existe déjà, il ne fait rien. Protège contre les rejeux de messages Messenger.

---

### 4. Symfony Messenger : pourquoi async pour les médias ?

L'extraction EXIF et la génération de thumbnail peuvent prendre plusieurs secondes sur de grosses images (RAW, vidéo). Faire ça dans la requête HTTP = timeout utilisateur.

**Solution** : après le `flush()` du File, on dispatch un `MediaProcessMessage` dans le bus. Le worker Messenger le consomme en arrière-plan.

| Environnement | Transport     | Pourquoi                                      |
|---------------|---------------|-----------------------------------------------|
| `prod/dev`    | `doctrine://` | Stockage en DB (`messenger_messages`), o2switch compatible, pas besoin de RabbitMQ |
| `test`        | `in-memory://`| Messages capturables via `$transport->get()` sans worker, tests rapides |

> **RabbitMQ** : non disponible sur o2switch mutualisé. Le transport Doctrine est suffisant pour un usage mono-utilisateur avec faible volume.

---

### 5. Sécurité fichiers : pourquoi blocage par extension et non par MIME ?

Le MIME type est fourni par le client — il peut être falsifié. Cependant, pour les exécutables, on bloque **l'extension** (plus fiable côté serveur) **ET** on fait confiance au `getClientMimeType()` pour le routing (détection image/vidéo).

**Pas de restriction de taille** : stockage illimité côté infra. La limite PHP (`upload_max_filesize`) est documentée dans `config/php.ini` et doit être déployée manuellement sur o2switch.

---

### 6. Stockage physique des fichiers

```
var/storage/
├── {year}/
│   └── {month}/
│       └── {uuid}.{ext}        ← fichiers originaux
└── thumbs/
    └── {uuid}.jpg              ← thumbnails (320px wide, JPEG q=80)
```

- **Chemin en DB** : relatif à `var/storage/` (ex : `2026/02/uuid.jpg`). Permet de déplacer le stockage sans migration DB.
- **`app.storage_dir`** : paramètre Symfony injecté dans `StorageService` et `ThumbnailService`. En prod, pointer vers un volume externe.

---

### 7. Tests fonctionnels API : choix techniques

- **`ApiTestCase`** (API Platform) plutôt que `WebTestCase` : client HTTP intégré avec assertions JSON.
- **`Accept: application/json`** obligatoire sur les collections : API Platform retourne `application/ld+json` par défaut (JSON-LD), ce qui change la structure (`hydra:member`, etc.).
- **Nettoyage DB** avec `SET FOREIGN_KEY_CHECKS=0` avant `DELETE` pour éviter les violations de FK entre tables liées (users → files → medias).
- **Pas de fixtures Doctrine** : données créées directement via l'EntityManager dans `setUp()` → plus rapide, plus explicite.

---

### 8. Audit sécurité — résultats et corrections (2026-02-27)

Audit réalisé avant merge de `feat/media`. Deux branches créées : `fix/security-upload` (3 correctifs critiques, mergée en premier) et `fix/security-hardening` (8 correctifs supplémentaires).

#### Branche `fix/security-upload`

| Sévérité | Problème | Fichier | Correction |
|----------|----------|---------|------------|
| 🔴 RCE | `.php`, `.phar`, `.phtml`, `.py`, `.rb`, `.asp`… non bloqués | `FileUploadController` | Ajout de toutes les extensions serveur dans `rejectExecutable()` |
| 🟡 Header | `addslashes()` pour `Content-Disposition` (invalide RFC 6266) | `FileDownloadController` | Remplacé par `HeaderUtils::makeDisposition()` |
| 🟡 Path traversal | `getAbsolutePath()` sans validation — chemin `../../etc/passwd` en DB passerait | `StorageService` | `realpath()` + vérification que le chemin reste sous `$storageDir` |

#### Branche `fix/thumbnail-cleanup`

| Sévérité | Problème | Fichier | Correction |
|----------|----------|---------|------------|
| 🟡 Fuite disque | Thumbnail non supprimé quand un File est supprimé (cascade DB enlève Media, pas le fichier) | `FileProcessor` | Charge le `Media` via `MediaRepository`, supprime `thumbnailPath` avant le flush |

#### Branche `fix/security-hardening`

| Sévérité | Problème | Fichier | Correction |
|----------|----------|---------|------------|
| 🔴 Config | `APP_SECRET` vide dans `.env` | `.env` / `.env.example` | Commentaire + template `.env.example` avec instructions `php bin/console secrets:generate-keys` |
| 🟠 Fuite info | `thumbnailPath` (chemin disque interne) exposé dans la réponse API | `MediaOutput` / `MediaProvider` | Renommé `thumbnailUrl` → génération d'une URL publique `/api/v1/medias/{id}/thumbnail` |
| 🟠 MIME spoofing | `Content-Type` en download issu de la DB (contrôlé par l'uploadeur) | `FileDownloadController` / `MediaThumbnailController` | `finfo_open()` revalidation au moment du download (MIME depuis le contenu réel du fichier) |
| 🟠 RAM DoS | `file_get_contents()` charge le fichier entier en RAM | `FileDownloadController` / `MediaThumbnailController` | Remplacé par `BinaryFileResponse` (streaming noyau, aucune lecture en RAM) |
| 🟡 Injection | `originalName` avec caractères de contrôle (`\x00`, `\n`, `\t`…) | `FileUploadController` | `preg_replace('/[\x00-\x1F\x7F]/u', '', $name)` avant persist |
| 🟡 Logique | `?type=` non validé → Doctrine `findBy(['type' => 'DROP TABLE'])` | `MediaProvider` | Validation contre `['photo', 'video', 'audio', 'document']`, `BadRequestHttpException` si invalide |
| 🟡 Validation | `newFolderName` sans limite de longueur ni vérification blank | `DefaultFolderService` | `trim()` → `''` → `InvalidArgumentException` ; `mb_strlen() > 255` → `InvalidArgumentException` |
| 🟡 Headers HTTP | Aucun header de sécurité global | `SecurityHeadersListener` | EventListener `kernel.response` → `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` |

**Ce qui était déjà sécurisé :**
- `var/storage/` hors de `public/` → non accessible directement par le webserver
- IDs UUID v7 → non énumérables
- Paths en DB issus de UUIDs générés par l'app → pas d'injection possible depuis l'URL
- Pas d'exposition des entités Doctrine → pas de fuite de champs sensibles

**Ce qui reste hors scope (intentionnel) :**
- Pas d'authentification (Phase future)
- Pas de rate limiting (mono-utilisateur, o2switch)
- Taille fichiers illimitée (choix utilisateur explicite)
- Mot de passe DB par défaut (hors scope dev local)

---

### 9. Chiffrement au repos — feat/encryption-at-rest

**Objectifs distincts :**
1. **Confidentialité** : si la DB est compromise (les paths de fichiers sont exposés), les fichiers sur disque sont illisibles sans `APP_ENCRYPTION_KEY`.
2. **Neutralisation** : les fichiers "sensibles" (SVG, HTML, JS…) sont stockés comme binaire chiffré opaque → non exécutables sur le serveur même en cas de path traversal.

**Algorithme : XChaCha20-Poly1305 (sodium secretstream)**
- Built-in PHP 8 (libsodium natif, zéro dépendance)
- Authentifié : détecte toute falsification du fichier (auth tag par chunk)
- Streaming : lecture/écriture par chunks de 8 Ko → aucune contrainte RAM pour les gros fichiers

**Format sur disque :**
```
HEADER (24 bytes) | chunk1_len (4B) | chunk1_chiffré | chunk2_len | chunk2_chiffré | ...
```

**Pipeline :**
| Étape | Comportement |
|-------|--------------|
| Upload (`StorageService::store`) | move() → encrypt() en place → fichier chiffré |
| Download (`FileDownloadController`) | decryptToTempFile() pour finfo MIME → StreamedResponse via decryptToStream() |
| Thumbnail génération (`ThumbnailService`) | decryptToTempFile() → GD → imagejpeg() → encrypt() thumbnail → unlink temp |
| EXIF (`ExifService`) | decryptToTempFile() → exif_read_data() → unlink temp |
| Thumbnail download (`MediaThumbnailController`) | decryptToStream() → StreamedResponse |

**Fichiers sensibles acceptés (neutralisés par chiffrement) :**
`svg`, `svgz`, `html`, `htm`, `xhtml`, `xml`, `xsl`, `xslt`, `js`, `mjs`, `css`

**Toujours bloqués (défense en profondeur) :**
`php`, `phar`, `exe`, `sh`, `py`, `rb`, `asp`, `jar`, et tous les exécutables OS

**Clé :**
- `APP_ENCRYPTION_KEY` dans `.env.local` (jamais commité)
- 32 bytes, base64 encodé
- Générer : `php -r "echo base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());"`

---

## ⚠️ Points d'attention
- **Versionnement API** : préfixer tous les endpoints `/api/v1/` (Orange API Guidelines)
- **DTOs** : ne jamais exposer les entités directement — toujours passer par des DTOs
- **Sécurité** : `APP_SECRET` à définir en prod, `APP_ENV=prod`
- **PHP ini** : copier `config/php.ini` dans `/etc/php/{version}/fpm/conf.d/99-homecloud.ini` au déploiement
