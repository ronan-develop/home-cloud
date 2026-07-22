# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-07-22

> **Status git :** `main` — dernière PR mergée #331 (`feat/311-media-viewer`)

---

## 🚧 Vignettes médias — explorer et vidéo (en cours, #312, branche `feat/312-vignettes-medias`)

- **Volet 1** ✅ : afficher les vignettes images dans « Mes fichiers ». Images existantes, câblage pur.
  - `ExplorerController` charge les Media en une requête, indexés par File.id (pas de N+1).
  - `FileCard.html.twig` affiche `<img>` quand `Media.thumbnailPath` existe, sinon icône SVG.
  - CSS : `.hc-item-icon--thumb` + `.hc-item-thumb-img` (cover-fit, 320×320px).
  - Test : `testFileCardShowsThumbnailWhenMediaExists` (vérifie l'affichage réel).
- **Volet 2** 🚧 : extraire et afficher les vignettes vidéo. Volet 1 mergeable seul ; volet 2 en dev.
  - Exceptions + interfaces + VO : `VideoThumbnailExtractionException`, `VideoThumbnailExtractorInterface`, `ExtractedVideoFrame`.
  - Implémentation : `VideoDurationProbe` (ffprobe), `VideoFrameGrabber` (ffmpeg), `FfmpegVideoThumbnailExtractor` (façade, 50 %).
  - Câblage `ThumbnailService` : branchement vidéo en premier (avant RAW), dégradation gracieuse.
  - `MediaProcessor` : génère la vignette pour les vidéos, pas d'EXIF.
  - Tests : `ThumbnailServiceVideoTest` (4 cas), `VideoThumbnailExtractorWiringTest`, `FfmpegVideoThumbnailExtractorTest` (skipé, ffmpeg absent localement).
  - Déploiement : script `bin/install-ffmpeg.sh` (idempotent, non-bloquant), intégré à `deploy-all.sh`/`deploy.sh` ; ffmpeg ajouté au CI (`.github/workflows/ci.yml`).

## ✅ Viewer PDF/média en plein écran (2026-07-22, #310, branche `feat/310-pdf-viewer-fullscreen`)

- `PdfViewerModal.html.twig` et `MediaViewerModal.html.twig` (#311) occupaient l'écran avec une marge (`max-w-5xl max-h-[90vh] mx-4 rounded-3xl`) au lieu du plein écran attendu par #310. Classes remplacées par `w-screen h-screen` (coins arrondis retirés, plus de sens à 100vw/100vh).
- Mécanisme d'ouverture/fermeture inchangé : `Modal.open/close` (`assets/js/modal.js`), pas de migration vers le WebComponent `<hc-modal>` (son `sizeMap.fullscreen` n'est pas branché sur ces deux modales et changer l'API aurait cassé les tests JS existants).
- Vérifié visuellement via Playwright (desktop 1440px + mobile 375px, PDF et image) : plein écran conforme, sans marge, header/bouton Fermer bien positionnés.
- **Limitation découverte, non corrigée dans ce ticket** : ni la touche Échap ni le clic sur l'overlay ne ferment ces deux modales (seul le bouton "Fermer" fonctionne) — absent de `modal.js`/`pdf-viewer-modal.js`/`media-viewer-modal.js` **avant** ce changement, donc pas une régression introduite ici. Le ticket #310 présupposait à tort que ce mécanisme existait déjà. Décision : rester dans le scope CSS (XS), traiter Échap/overlay dans un ticket séparé si souhaité.
- Aucun test unitaire modifié : changement purement CSS, `testViewButtonAppearsOnlyForPdfFiles` et `pdf-viewer-modal.test.js` restent verts sans modification (filet de non-régression).

## ✅ Viewer média (image/vidéo) dans « Mes fichiers » (2026-07-22, #311, PR #331)

- Bouton "Visualiser" ajouté sur les cartes fichier image/vidéo dans `FileCard.html.twig`, sur le même modèle que le bouton PDF existant (#241) — `data-testid="view-media-btn-{id}"`, distinction image/vidéo par `mimeType`.
- Nouvelle modale `MediaViewerModal.html.twig` (`<img>`/`<video controls>` natifs, un seul élément affiché à la fois selon le type) et `assets/js/media-viewer-modal.js` — même helper `Modal` (`assets/js/modal.js`) que les autres modales du projet.
- Fermeture vidéo : `pause()` + `removeAttribute('src')` + `load()`, pas seulement `about:blank` (suffisant pour l'iframe PDF mais insuffisant pour un `<video>` — sans ça le flux continue de streamer/décoder en arrière-plan).
- Aucune modification serveur : réutilise `app_file_view`, déjà générique (`DISPOSITION_INLINE` + ownership) depuis #241.
- TDD : `testViewButtonAppearsOnlyForImageAndVideoFiles` (`ExplorerPageTest.php`) + `media-viewer-modal.test.js` (4 cas Jest : ouverture image, ouverture vidéo, fermeture propre, bascule image↔vidéo).
- Mergé et déployé sur les 7 instances o2switch ; **vérification manuelle confirmée OK** par l'utilisateur sur `ronan.lenouvel.me` (desktop + mobile, upload/visualisation/fermeture image et vidéo).
- **Bug latéral trouvé et corrigé pendant le déploiement** : `.secrets` contenait deux lignes non commentées (`login : ...` / `mot de passe : ...`) sous forme de note en clair — sourcées en bash par `deploy-all.sh`, `login` était exécuté comme commande système et cassait le script avant la connexion SSH. Commentées.
- **Ticket #310 découvert en parallèle** (viewer PDF en plein écran, demandé par l'utilisateur, déjà ouvert) : la modale PDF actuelle est contrainte (`max-w-5xl max-h-[90vh]`), pas encore traité.

## ✅ Renommer un album (2026-07-22, #242)

- `AlbumService::rename()` ajouté (délègue à `Album::setName()`, déjà existant avec trim/validation), route `POST /albums/{id}/rename` sur `AlbumWebController` (CSRF + `AlbumVoter::VIEW`, cohérent avec les autres actions d'édition d'album accessibles aux guests en partage write — `add-media`, `reorder`, `set-cover`, `import`).
- UI : bouton crayon dans l'en-tête de `album_detail.html.twig`, `prompt()` natif pour saisir le nouveau nom (cohérent avec `confirm()` déjà utilisé pour la suppression), form POST classique + redirect — pas de nouveau contrôleur Stimulus.
- TDD complet : `tests/Service/AlbumServiceTest.php` (happy path, nom vide/whitespace, non-save si invalide) + `tests/Web/AlbumRenameWebTest.php` (redirect, persistance, CSRF invalide → 403, autre utilisateur → 403, nom vide → 400).

## ✅ Déploiement, gestion des instances (2026-07-20)

- **7ᵉ instance `baptiste.lenouvel.me`** ajoutée : base MySQL créée via `uapi Mysql create_database`/`set_privileges_on_database` (utilisateur partagé `ron2cuba_ronan`, comme les 6 autres instances — divergence constatée entre la doc `deploiement.md` qui documentait un utilisateur par instance et la pratique réelle), premier déploiement manuel, premier compte créé, puis ajoutée à `.deploy-targets` pour les mises à jour futures.
- **Webhook de déploiement cassé découvert** : `public/deploy.php` (déclenché par un webhook GitHub natif, pas une Action) recevait un 401 "Invalid signature" sur toutes les livraisons depuis plusieurs jours — secret désynchronisé entre GitHub et le serveur. N'était de toute façon plus le mécanisme réel de déploiement (voir ci-dessous), corrigé dans la doc (`cicd.md`, `DEPLOY_WORKFLOW.md`, `DEPLOY_SECRETS.md`).
- **Ticket #288** (industrialiser le déploiement via GitHub Actions) : bloqué par l'absence d'IP fixe pour un runner self-hosted. Piste alternative trouvée : l'API `SshWhitelist` d'o2switch (port cPanel 2083) permet de whitelister dynamiquement l'IP d'un runner GitHub-hosted avant chaque déploiement. Authentification recommandée (token API cPanel) indisponible sur ce compte ("bloqué à des fins de sécurité" — réponse du support). **Décision actée : rester sur le déploiement manuel `bin/deploy-all.sh` pour l'instant**, ticket laissé ouvert pour référence.
- **Piège IP dynamique / VPN d'entreprise** identifié en pratique : sans IP fixe, le SSH se bloque silencieusement (timeout) dès que l'IP whitelistée change — rencontré 3 fois dans la même session. L'API `SshWhitelist` permet de re-whitelister sans accès SSH préalable (identifiants cPanel classiques en repli, le token étant indisponible).
- `bin/deploy-all.sh` avait perdu son bit exécutable (PR #289).

## ✅ Page changelog auto-alimentée (2026-07-20, #290)

Page `/changelog`, accessible depuis la navigation, listant les grandes évolutions du projet.

- `GitHubChangelogFetcher` (PR #292) : interroge l'API GitHub (PR mergées), filtre par label `feature`/`bug`/`securité`/`performance` — le reste (chore, refactor, ci, docs, tests) reste filtré comme bruit interne. Résultat mis en cache 1h (`cache.app`), aucune table dédiée en base — GitHub reste la seule source de vérité.
- **Historique complet paginé** (PR #294) : le fetcher parcourt désormais toutes les pages GitHub jusqu'à épuisement (garde-fou 20 pages) plutôt que de se limiter aux 100 PR les plus récentes ; affichage paginé côté page (20 entrées/page).
- **Dates au format français** (PR #296) : `jj/mm/aaaa` plutôt que l'ISO brut renvoyé par l'API.
- **`PrTitleCleaner` extrait du fetcher** (PR #297, SRP) : le nettoyage de titre (retrait emoji + préfixe conventionnel `type(scope):`) est un service indépendant (`PrTitleCleanerInterface`), injecté par DIP. Corrige au passage plusieurs bugs réels : emoji suivi d'un variation selector (`🛡️` = U+1F6E1 + U+FE0F) non reconnu, titre avec emoji mais sans deux-points, titre en prose avec un deux-points plus loin dans la phrase.
- **`#` markdown en tête de titre** (PR #298) : un `#` collé par erreur devant l'emoji sur d'anciens titres de PR (ex: `# 🏷️ PR : ...`) bloquait aussi la détection de l'emoji — corrigé.
- **Ticket #293** ouvert : notification visuelle (icône + badge) pour signaler de nouvelles entrées changelog — décision actée que le marqueur "dernière visite" ira sur `User` (DB), pas en `localStorage`.

## ✅ Sécurité — viewer PDF et fichiers actifs (2026-07-20, #280/#286)

- **#280 / PR #287** : `X-Frame-Options: DENY` et `frame-ancestors 'none'` bloquaient l'iframe same-origin du viewer PDF (#241) — dérogation strictement scopée à `/files/{id}/view` (`SAMEORIGIN`/`frame-ancestors 'self'`), les autres routes gardent la protection anti-clickjacking complète.
- **#286 / PR #291** : un PDF peut embarquer du JavaScript exécutable (`/OpenAction`, `/AA`, `/JS`, `/Launch`) — détecté à l'upload (recherche de motif délimité, évite le faux positif `/JSON` confondu avec `/JS`) et neutralisé comme les autres types dangereux (même mécanisme que HTML/SVG). Limite documentée : les objets compressés en flux `/ObjStm` échappent à cette détection (heuristique, pas un vrai parseur PDF).
- **Bug latéral découvert et corrigé** (même PR #291) : un PDF valide mais dont l'en-tête `%PDF-` est décalé au-delà de l'octet 0 (ex: fichier téléchargé depuis un site tiers ayant laissé fuiter du texte de debug en préfixe) était servi en `application/octet-stream` par `finfo`, forçant un téléchargement au lieu du rendu inline malgré la tolérance de la norme PDF (ISO 32000-1 §7.5.2, en-tête toléré dans les 1024 premiers octets). `PdfSignatureDetector` reproduit cette tolérance.
- **Tickets de suivi ouverts** : #285 (dérogation CSP basée sur le path plutôt que `_route`, fragile), #276 (redirection changement de mot de passe à la première connexion — toujours pas implémenté, pertinent pour tout compte créé avec un mot de passe temporaire).

## ✅ Centralisation des factories (2026-07-20, PR #295)

`ShareLinkFactory` (`App\Security` → `App\Factory`) et `MediaFullResponseFactory` (`App\Service` → `App\Factory`) déplacées vers `src/Factory/`, cohérent avec `FolderTreeFactory` déjà présente à cet endroit. Déplacement pur, aucun changement de comportement.

## ✅ Téléchargement de dossier en ZIP (2026-07-20, PR #274)

`FolderZipArchiver` : télécharger un dossier entier (avec sa structure) en une seule archive.

## ✅ Glisser-déposer un dossier local (2026-07-20, PR #279)

Upload d'un dossier complet depuis le disque, avec sa structure, par glisser-déposer dans l'explorateur.

## ✅ Fix barre de progression upload multiple (2026-07-20, PR #275)

## ⚠️ Ticket ouvert — révocation d'un partage par compte (2026-07-20, #299)

Aucune route de révocation n'existe pour un `Share` (partage par compte à un invité) — seul `ShareLink` (lien public) peut être révoqué. Trou fonctionnel de bout en bout (backend + frontend), pas juste un oubli d'UI.

---

## ✅ Viewer PDF inline (2026-07-19, #241 partie 1 — fermé)

Bouton "Visualiser" pour afficher un PDF dans le navigateur (iframe, `Content-Disposition: inline`) au lieu de forcer le téléchargement.

- `FileWebController` : nouvelle route/paramètre servant le PDF en `inline`, route `download` existante inchangée (`attachment`)
- Modale front avec `<iframe>` sur `FileCard.html.twig`
- Reste à faire (partie 2 de #241, non trackée) : vignette de la 1ère page dans la grille via Ghostscript

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
- **`MAILER_DSN`** activé sur les 6 instances (`ronan` + `yannick`/`coralie`/`elea`/`corentin`/`damien`, 2026-07-20, cf. `deploiement.md`) — sans lui, le traitement fonctionne mais l'email « lot prêt » ne part pas. `bin/deploy-all.sh --init` l'injecte désormais automatiquement pour toute nouvelle instance.
- **Seuil** `hc.upload.deferred_threshold_bytes` = 250 Mo par défaut, ajustable dans `config/services.yaml`.

Plan détaillé : `.claude/plans/je-suis-d-accord-avec-ticklish-zebra.md`. Connexes restantes : #245 (curseur de concurrence d'upload), #239 (barre de progression) — même zone `upload-modal.js`.

---

## ✅ EXIF pack photographe (2026-07-19, #268)

Réglages de prise de vue (RAW + JPEG) lus et affichés.

---

## ✅ Notification de partage asynchrone (2026-07-19, #270)

`ShareNotificationMailer::notify()` passe désormais par Messenger (message + worker/transport dédié) au lieu d'un envoi synchrone bloquant la requête `/share-create`.

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
5. ✅ **FAIT — Notification de partage asynchrone** (#270) : `ShareWebController` dispatche un `ShareNotificationMessage` (routé vers le transport `async`) au lieu d'appeler `ShareNotificationMailer::notify()` en synchrone → `/share-create` ne bloque plus sur le SMTP (× nombre d'invités). `ShareNotificationHandler` consomme et envoie. Périmètre volontaire : notification de partage seule — reset-password, invitation invité et batch restent synchrones (worker = cron 5 min, on ne retarde pas le reset-password)
6. **Nettoyer les ~168 notices PHPUnit** — `createMock()` utilisé sans `expects()` déclenche une notice PHPUnit 13 ("Consider refactoring your test code to use a test stub instead") ; concentrées dans `tests/Unit/{Entity,EventListener,Security,Service}`, notamment `ShareLinkFactoryTest.php` (~15) ; remplacer ces `createMock()` par `createStub()` là où aucune expectation n'est réellement vérifiée (le chantier worker média a déjà converti quelques cas) — suivi dans #272
7. ✅ **FAIT — Pack photographe EXIF (RAW + JPEG)** (#268) : `ronanlenouvel/raw-preview-extractor` **1.2.0** expose `ExtractedPreview->metadata` (date, ouverture, vitesse, ISO, focale, objectif, appareil), lu depuis l'EXIF du RAW ; `ExifService` fait de même pour les JPEG. `Media` gagne 5 colonnes (aperture, shutter_speed, iso, focal_length, lens), exposées par l'API, affichées dans un panneau EXIF du lightbox galerie, avec tri « date de prise de vue ». CR3 : métadonnées `null` pour l'instant (parseur ISO-BMFF, piste d'itération)
8. **Optimiser le redimensionnement des grandes previews** — une preview RAW pleine résolution (8256×5504) prend ~2,9 s à redimensionner sous GD, contre 23 ms pour l'extraction elle-même. Acceptable car le pipeline média est asynchrone (Messenger), mais un redimensionnement en deux passes réduirait nettement le coût
9. **Chiffrement au repos** — `APP_ENCRYPTION_KEY` est déclaré dans `config/services.yaml` mais injecté nulle part : les fichiers sont stockés en clair. Prévu comme option laissée à l'utilisateur. Incohérence résiduelle : le docblock `@param` de `ThumbnailService::generate()` (ligne 43) dit encore « chiffrée sur disque » alors que le reste de la classe (ligne 24) indique correctement « stockés en clair » — à corriger
10. ✅ **RÉSOLU** — ~~Extensions PHP sous-déclarées~~ : `composer.json` requiert désormais `ext-ctype`, `ext-iconv`, **`ext-gd`** et **`ext-exif`**. Plus de risque d'échec silencieux à l'exécution
11. **`CLAUDE.md` annonce une PWA inexistante** — aucun `manifest.json` ni service worker dans le projet (vérifié). `CLAUDE.md` mentionne toujours « PWA » dans la stack. Soit implémenter (installable, cache offline des vignettes), soit retirer la mention

