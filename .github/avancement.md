# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-02-27

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

---

## 🚧 En cours

- **feat/file-upload** — Phase 2 terminée, en attente de merge dans `main`

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

### 🔵 Phase 3 — Médias

- [ ] **Media** — Entity + migration + ApiResource (enrichit File : EXIF, thumbnail, type photo/vidéo)
  - `GET /api/v1/medias` (filtrable par type, date, album)
  - `GET /api/v1/medias/{id}`

### 🔵 Phase 4 — Albums _(à venir)_

- [ ] **Album** — collection de Media, sans structure de dossier

### 🔵 Phase 5 — Domotique / Dashboard _(futur)_

- [ ] À définir

---

## ⚠️ Points d'attention

- **Base de données** : passer sur **MySQL/MariaDB 10.6** pour la prod o2switch (PostgreSQL 9.2 trop ancien)
- **Versionnement API** : préfixer tous les endpoints `/api/v1/` (Orange API Guidelines)
- **DTOs** : ne jamais exposer les entités directement — toujours passer par des DTOs
- **Sécurité** : `APP_SECRET` à définir en prod, `APP_ENV=prod`
- **PHP ini** : copier `config/php.ini` dans `/etc/php/{version}/fpm/conf.d/99-homecloud.ini` au déploiement
