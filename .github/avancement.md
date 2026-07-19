# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-07-19

> **Status git :** `main` — chantier worker média mergé (PRs #262, #265, #264)

---

## ✅ Worker média : routage lots lourds / petits lots — TERMINÉ (2026-07-19)

Le worker Messenger ne servait plus à rien : chaque upload faisait **les deux** — `bus->dispatch(MediaProcessMessage)` **et** `collector->add()` (traitement immédiat sur `kernel.terminate`) — le worker refaisant un **no-op** grâce à l'idempotence de `MediaProcessor::process()`.

Refonte livrée : le worker ne tourne **que pour les lots lourds**. Petit lot → traitement immédiat (latence perçue nulle). Lot lourd (**taille cumulée > seuil OU présence d'un RAW**) → déporté au worker, avec **notif email + toast** en fin de traitement. Le serveur décide du routage (le JS ne fait que l'UX) ; corrélation des fichiers d'un même envoi par `batchId`.

Contraintes o2switch (mutualisé) : pas de daemon → worker = **cron 5 min** conservé ; **Mercure exclu** → notif écran par **polling court** (jamais de long-polling).

| Lot  | Issue | PR mergée | Contenu                                                                                       |
|------|-------|-----------|-----------------------------------------------------------------------------------------------|
| PR 1 | #259  | #262      | `UploadBatch` + seuil (`UploadRoutingDecider`) + routage exclusif immediate/deferred (fin du double dispatch) |
| PR 2 | #260  | #265      | Email de fin de lot différé (`BatchCompletionNotifier` + `MediaProcessHandler`)                |
| PR 3 | #261  | #264      | Endpoint statut + polling front (`upload-batch.js`) + toast                                    |

Points d'attention post-merge :
- **`MAILER_DSN`** à activer sur chaque instance (`.env.prod.local`, cf. `deploiement.md`) — sans lui, le traitement fonctionne mais l'email « lot prêt » ne part pas.
- **Seuil** `hc.upload.deferred_threshold_bytes` = 250 Mo par défaut, ajustable dans `config/services.yaml`.

Plan détaillé : `.claude/plans/je-suis-d-accord-avec-ticklish-zebra.md`. Connexes restantes : #245 (curseur de concurrence d'upload), #239 (barre de progression) — même zone `upload-modal.js`.

---

## ✨ Couverture explicite d'album (2026-07-15)

Après le merge de la fonctionnalité de partage par lien public (PR #216), ajout du choix explicite de la vignette de couverture d'un album — jusqu'ici uniquement un fallback automatique (premier média avec thumbnail).

- `Album::coverMedia` (ManyToOne nullable vers `Media`, `ON DELETE SET NULL`) — migration `Version20260715110348`
- `Album::setCoverMedia()` valide que le média appartient à l'album (`InvalidArgumentException` sinon) ; `removeMedia()` réinitialise la couverture si le média retiré était la couverture actuelle
- `Album::resolveCoverMedia()` centralise la logique d'affichage : couverture explicite si elle a un thumbnail, sinon fallback sur le premier média par position qui en a un — réutilisable partout où une vignette d'album est nécessaire
- Route `POST /albums/{id}/set-cover` (CSRF + `AlbumVoter::VIEW`, 400 si le média n'appartient pas à l'album), suit le pattern des routes POST dédiées déjà en place (`reorder`, `remove-media`)
- Bouton "définir comme couverture" sur chaque vignette de `album_detail.html.twig` (masqué si déjà couverture) + badge visuel sur la couverture actuelle — design du badge volontairement minimal pour l'instant, à retravailler
- Cartes de `/albums` affichent désormais une vraie vignette (`resolveCoverMedia()`) au lieu de systématiquement l'icône générique

---

## ✨ Tri de la galerie médias (2026-07-12)

Ajout d'un menu de tri sur `/gallery` (Plus récent, Plus ancien, Nom A→Z/Z→A, Taille croissante/décroissante).

- Réutilise la convention `?order[champ]=direction` déjà en place dans `FileRepository`/`FolderRepository` (PR #168) plutôt que d'en créer une nouvelle — `MediaRepository::findByOwner()` accepte désormais un `array $orderBy` avec la même whitelist et le même comportement d'ignorance silencieuse des champs inconnus (pas de 400, cohérence avec l'API)
- `<select>` natif dans `gallery.html.twig`, propage le tri courant dans les liens de filtre photo/vidéo pour que les deux se combinent
- `WebFixturesTrait::createMediaFile()` étendu (`$size`, `$createdAt` optionnels, Reflection pour `createdAt` qui n'a pas de setter en prod) pour pouvoir tester le tri

---

## 🎨 Alignement design /albums sur le dashboard (2026-07-12)

Migration de `albums.html.twig` et `album_detail.html.twig` vers le design system `--hc-*`, troisième étape après `/explorer` (#186) et `/gallery` (#187).

- `assets/styles/albums.css` créé : grille de cartes, réutilise `.hc-item-card`/`.hc-item-icon` (explorer.css) et `.hc-media-*` (gallery.css) plutôt que de dupliquer
- Suppression de tous les styles inline et hex en dur (`#111827`, `#3b82f6`, `#ef4444`, `#e5e7eb`, `#d1d5db`, `#9ca3af`, `#6b7280`, `#f3f4f6`)
- Réutilisation du composant `EmptyState` partagé pour les deux pages (liste vide, album sans média)
- En-tête de page ajouté (titre + décompte d'albums)

---

## 🎨 Alignement design /gallery sur le dashboard (2026-07-12)

Migration de la page `/gallery` (Galerie médias) vers le design system `--hc-*`, dans la continuité du redesign `/explorer` (PR #186).

- `assets/styles/gallery.css` créé : pilules de filtre, grille de vignettes, lightbox — cohérents avec `dashboard.css`/`explorer.css`
- `gallery.html.twig` entièrement réécrit : suppression de tous les styles inline et hex en dur (`#111827`, `#3b82f6`, `#e5e7eb`, `#374151`, `#9ca3af`, `#f3f4f6`)
- Emoji `📷` remplacé par une icône SVG Heroicons
- Réutilisation du composant `EmptyState` partagé (déjà migré lors du redesign `/explorer`)
- En-tête de page ajouté (titre + décompte de médias)

---

## 🎨 Alignement design /explorer sur le dashboard (2026-07-12)

Migration de la page `/explorer` (Mes fichiers) vers le design system `--hc-*` déjà en place sur le dashboard (PR #183/#185). Voir [plan.md](../plan.md) pour le détail.

- `assets/styles/explorer.css` créé : cartes, pastilles icône, actions, dropzone, état vide — cohérents avec `dashboard.css`
- Sidebar dossiers (`layout.css`) migrée vers les variables `--hc-*` ; suppression d'un bloc `@media (prefers-color-scheme: dark)` redondant qui ignorait le toggle `[data-theme]`
- `ImportCard`, `FolderCard`, `FileCard`, `EmptyState`, `Breadcrumbs` restylés (fini les hex en dur type `#a5b4fc`, `text-white`, dégradés violets)
- En-tête de page ajouté (`h1` + décompte dossiers/fichiers), `ExplorerController` passe désormais `folderCount`/`fileCount`
- Corrigé au passage : `login()` dans `ExplorerPageTest` suivait la redirection post-login vers `/dashboard` (commit #185) au lieu de rester sur `/explorer` — 6 tests cassés remis au vert

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

- **781 tests PHPUnit** (~1648 assertions), 0 failures, 0 errors, 2 skipped
- **82 tests Jest** (front)
- +35 tests depuis le chantier worker média (#259/#260/#261)

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
3. **Badge de couverture d'album** — design trop brut (icône étoile sur fond noir), à retravailler visuellement
4. **Rendre les interactions asynchrones (fetch/AJAX) au lieu de POST + reload complet** — ✅ fait pour create/edit/delete de la gestion des invités (`guest_management_controller.js`) ; reste à généraliser le même pattern (détection `Accept: application/json` + fallback progressif + contrôleur Stimulus) aux autres actions POST+redirect encore existantes (albums, dossiers, fichiers, partages...)
5. **Passer l'envoi des emails de notification en asynchrone (Messenger)** — `ShareNotificationMailer::notify()` envoie actuellement de façon synchrone pendant la requête `/share-create`, ce qui bloque la réponse le temps de l'envoi ; faire passer par un message Messenger + worker/transport dédié
6. **Nettoyer les 141 notices PHPUnit** — `createMock()` utilisé sans `expects()` déclenche désormais une notice PHPUnit 13 ("Consider refactoring your test code to use a test stub instead") ; concentrées dans `tests/Unit/{Entity,EventListener,Security,Service}`, surtout `ShareLinkFactoryTest.php` (10) et `VisibilityCheckerTest.php` (12) ; remplacer ces `createMock()` par `createStub()` là où aucune expectation n'est réellement vérifiée
7. **EXIF des fichiers RAW** — `ExifService` s'appuie sur `exif_read_data()`, qui ne lit pas les conteneurs RAW : date de prise de vue, modèle d'appareil et GPS restent vides pour ces photos (la vignette, elle, fonctionne depuis l'intégration de `ronanlenouvel/raw-preview-extractor`). Deux pistes : lire les EXIF de la preview JPEG extraite (simple, mais métadonnées appauvries), ou exposer les tags TIFF depuis le package (plus riche — réglages complets, résolution capteur réelle)
8. **Optimiser le redimensionnement des grandes previews** — une preview RAW pleine résolution (8256×5504) prend ~2,9 s à redimensionner sous GD, contre 23 ms pour l'extraction elle-même. Acceptable car le pipeline média est asynchrone (Messenger), mais un redimensionnement en deux passes réduirait nettement le coût
9. **Chiffrement au repos** — `APP_ENCRYPTION_KEY` est déclaré dans `config/services.yaml` mais injecté nulle part : les fichiers sont stockés en clair. Prévu comme option laissée à l'utilisateur. À noter, un docblock de `ThumbnailService::generate()` prétend déjà que la source est « chiffrée sur disque » — à corriger en même temps
10. **Extensions PHP sous-déclarées** — `composer.json` ne requiert que `ext-ctype` et `ext-iconv`, alors que `gd` (`ThumbnailService`, `MediaFullResponseFactory`) et `exif` (`ExifService`) sont indispensables. Une install sur un serveur sans ces extensions passerait `composer install` puis échouerait silencieusement à l'exécution
11. **`CLAUDE.md` annonce une PWA inexistante** — aucun `manifest.json` ni service worker dans le projet. Soit implémenter (installable, cache offline des vignettes), soit retirer la mention

