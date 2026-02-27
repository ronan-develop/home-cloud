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
| 2026-02-27 | Setup PHPUnit 13 + symfony/test-pack — 3 tests / 9 assertions ✅                |

---

## 🚧 En cours

> _(rien pour l'instant)_

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

### 🔵 Phase 2 — Fichiers

- [ ] **File** — Entity + migration + ApiResource (upload, lié à Folder + User)
  - `GET /api/v1/files` (filtrable par folder)
  - `POST /api/v1/files`
  - `GET /api/v1/files/{id}`
  - `DELETE /api/v1/files/{id}`

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
