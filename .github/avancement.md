# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-02-27 (Phase 3 Media complète)

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

---

## 🚧 En cours

- **feat/media** — Phase 3 terminée (38/38 tests), en attente de merge dans `main`

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



- **Base de données** : passer sur **MySQL/MariaDB 10.6** pour la prod o2switch (PostgreSQL 9.2 trop ancien)
- **Versionnement API** : préfixer tous les endpoints `/api/v1/` (Orange API Guidelines)
- **DTOs** : ne jamais exposer les entités directement — toujours passer par des DTOs
- **Sécurité** : `APP_SECRET` à définir en prod, `APP_ENV=prod`
- **PHP ini** : copier `config/php.ini` dans `/etc/php/{version}/fpm/conf.d/99-homecloud.ini` au déploiement
