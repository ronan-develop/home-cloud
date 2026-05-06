# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-05-06

> **Status git :** `main` — PR #169 mergée — 334 tests ✅

---

## ✅ Refactoring SOLID/KISS/DRY — TERMINÉ (2026-03-16)

Toutes les vagues du plan de refactoring sont complètes. PRs mergées :

| PR | Branche | Contenu |
|----|---------|---------|
| #136 | `refactor/AuthenticationResolver` | AuthenticationResolver injecté dans FolderProcessor/AlbumProcessor/FolderProvider + IriExtractor (extraction UUID depuis IRI) |
| #137 | `feat/RepositoryInterfaces` | FolderRepositoryInterface, UserRepositoryInterface, ShareRepositoryInterface (DIP) |
| #138 | `refactor/OwnershipChecker` | OwnershipChecker — centralise les vérifications de propriété (5 occurrences éliminées) |
| #139 | `refactor/AlbumRepositoryInterface` | AlbumRepositoryInterface dans les controllers et processor |
| #140 | `refactor/FolderProcessorSRP` | FolderService extrait de FolderProcessor (SRP) + 3 interfaces DIP |
| #141 | `chore/MoveInterfacesToInterfaceDir` | FolderMoverInterface + PasswordResetServiceInterface → src/Interface/ |
| #142 | `chore/ReorganizeServiceDir` | src/Factory/ (FolderTreeFactory) + src/Security/ (AuthenticationResolver, AuthorizationChecker, ShareAccessChecker) |
| #143 | `refactor/ExceptionStyle` | InvalidArgumentException → BadRequestHttpException dans tous les services |

### Architecture finale

```
src/
├── Controller/         ← HTTP uniquement, délègue aux services
├── Factory/            ← FolderTreeFactory
├── Interface/          ← 14 contrats DIP
├── Repository/         ← accès données (implémentent les interfaces)
├── Security/           ← AuthenticationResolver, AuthorizationChecker, OwnershipChecker, ShareAccessChecker
├── Service/            ← logique métier (FolderService, AlbumService, FileActionService…)
├── State/              ← processors/providers API Platform (dispatchers HTTP → services)
└── Entity/             ← entités Doctrine
```

### Principes appliqués
- **SRP** : FolderProcessor réduit à dispatcher pur (~115 lignes, était ~260)
- **DIP** : 14 interfaces, zéro dépendance concrète dans les processors
- **DRY** : auth, ownership, IRI extraction — chacun centralisé une fois
- **Testabilité** : tous les services mockables via leurs interfaces

---

## ✅ Audit Sécurité — TERMINÉ (2026-03-26)

Score global : **9/10** — 4/5 axes de remédiation implémentés.

| Point audité | Résultat |
|---|---|
| Upload validation (MIME, extension, path traversal) | ✅ Excellent |
| JWT RS256 + Refresh Token rotation | ✅ OK |
| Autorisation (OwnershipChecker + Voter) | ✅ OK |
| Mots de passe (argon2id/bcrypt) | ✅ OK |
| CORS (regex serrée en prod) | ✅ OK |
| Security Headers (CSP, X-Frame-Options…) | ✅ OK |
| SQL injection (QueryBuilder paramétrisé) | ✅ OK |
| Rate limiting sur /api/v1/auth/login | ✅ Fait (PR #146 — 5 req / 15 min, HTTP 429) |
| Auth failure logging | ✅ Fait (PR #146 — email, IP, user-agent) |
| HSTS header | ✅ Fait (PR #146 — prod uniquement, max-age=31536000) |
| `composer audit` en CI | ✅ Fait (PR #147 — step avant les tests) |
| Assert sur DTOs | ✅ Fait (PR #167 — `#[Assert\Email]`, `#[Assert\Length]`, `#[Assert\NotBlank]` sur UserOutput et FolderOutput, violations 422) |

### CI/CD — Node.js 24

- `FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true` ajouté (PR #147)
- `node-version` mis à jour vers `22` (LTS)

→ Détail des tâches restantes : `.github/todo-security.md`

---

## ✅ Sécurité — DELETE File ownership check (2026-04-16)

| PR | Branche | Contenu |
|----|---------|---------|
| #159 | `fix/file-delete-ownership-check` | `OwnershipChecker` étendu à `File` + `denyUnlessOwner` dans `FileProcessor::handleDelete` — tout user authentifié pouvait supprimer les fichiers d'autrui |
| #159 | `fix/file-delete-ownership-check` | `FileDeleteTest` : 3 tests fonctionnels DELETE 204/403/404 |
| #159 | `fix/file-delete-ownership-check` | Bug latent `MediaTest` corrigé (mauvais user dans `createAuthenticatedClient`) |
| #159 | `fix/file-delete-ownership-check` | `.deploy-targets` ajouté au `.gitignore` |

## ✅ Frontend — Breadcrumb icône home (2026-04-16)

| PR | Branche | Contenu |
|----|---------|---------|
| #149 | `feat/breadcrumb-home-icon` | Icône home SVG Heroicons sur le lien racine du fil d'Ariane (remplace "Tous les fichiers") — `aria-label="Accueil"`, hérite la couleur du thème |

## ✅ Frontend — RenameModal (2026-04-16)

| PR | Branche | Contenu |
|----|---------|---------|
| #162 | `feat/rename-modal` | Remplace `prompt()` par une modale glassmorphism — `openRenameModal()` / `submitRename()`, validation inline, toast, Entrée/Échap |

## ✅ Frontend — Page paramètres utilisateur (2026-04-17)

| PR | Branche | Contenu |
|----|---------|---------|
| #165 | `feat/user-settings-patch` | Route `/settings`, `UserSettingsController`, formulaires profil + mot de passe, PATCH `/api/v1/users/{id}`, `UserProcessor`, `UserPatchTest` (200/403/404/422) |
| #166 | `style/settings-css-refactor` | Extraction des styles inline → `settings.css` (`.settings-card`, `.settings-input`…), `.btn`/`.btn-primary` dans `button.css`, suppression SVG filter glass-distort |

## ✅ API — Pagination/tri/recherche Folders & Files (2026-04-17)

| PR | Branche | Contenu |
|----|---------|---------|
| #168 | `feat/folder-file-filters` | `FolderRepository::findFiltered/countFiltered` + `FileRepository::findFiltered/countFiltered` ; `FolderProvider`/`FileProvider` lisent `?name=`, `?originalName=`, `?order[field]=asc\|desc` ; `FolderFilterTest` + `FileFilterTest` (8 tests) |

## ✅ Sécurité — Assert sur DTOs (2026-04-17)

| PR | Branche | Contenu |
|----|---------|---------|
| #167 | `feat/assert-dto-constraints` | `#[Assert\Email]`, `#[Assert\Length]` sur `UserOutput` ; `#[Assert\NotBlank(groups:create)]` + `#[Assert\Length]` sur `FolderOutput::name` ; injection `ValidatorInterface` dans processors ; 422 retourne désormais `violations` |

## ✅ API + Frontend — Validation unicité & fix drag & drop (2026-05-06)

| PR | Branche | Contenu |
|----|---------|---------|
| #169 | `feat/uniqueness-validation` | `findOneByNameInFolder` dans `FileRepository` ; unicité vérifiée sur rename + move dans `FileActionService` et `FolderService` (parent effectif) ; `FileUniquenessTest` (4 tests) + `FolderUniquenessTest` (3 tests) |
| #169 | `feat/uniqueness-validation` | Fix drag & drop : écoute sur `document` entier avec compteur anti-flickering ; `folderId` du fil d'ariane propagé jusqu'à la destination d'upload (`hc-folder-list`, `upload-modal`) |

## ✅ Chore — Cleanup code mort (2026-04-16)

| Fichier supprimé | Raison |
|-----------------|--------|
| `assets/controllers/hello_controller.js` | Exemple Stimulus jamais utilisé |
| `assets/controllers/new_menu_controller.js` | Jamais appliqué via `data-controller` |
| `assets/controllers/file_upload_controller.js` | Idem |
| `templates/components/Modal.html.twig` | Alpine.js, jamais inclus |
| `templates/components/Button.html.twig` | Orphelin |
| `templates/components/folder_browser.html.twig` | TDD RED abandonné |
| `tests/Web/FolderBrowserComponentTest.php` | Couplé au template supprimé |
| `.env APP_SHARE_DIR` | Variable jamais utilisée |

---

## 📊 État des tests

- **334 tests**, ~686 assertions
- 0 skipped, 0 failures, 0 errors
- +7 tests depuis PR #169 (unicité fichier/dossier)

---

## ✅ CI/CD — Pipeline complet (2026-03-26)

| Étape | Workflow | Déclencheur |
|-------|----------|-------------|
| Tests PHP (PHPUnit + MariaDB) | `CI/php` | push / PR sur `main` |
| Tests JS (Jest) | `CI/js` | push / PR sur `main` |
| Audit dépendances | `CI` → `composer audit` | push / PR sur `main` |
| Déploiement auto | `Deploy` | CI ✅ sur `main` |
| Déploiement manuel | `Deploy` | `workflow_dispatch` |

→ Tout merge dans `main` avec CI verte déclenche automatiquement le déploiement sur `ronan.lenouvel.me`.

---

## 🗺️ Prochaines pistes

### Reste à faire
1. **Redesign UI** — intégration du design system `demo/` (tokens CSS, Geist, glassmorphism, mode clair/sombre, responsive)
2. **Documentation OpenAPI** à jour (filtres, codes 400 unicité, PATCH User)

