# Directions techniques

Les partis pris structurants et leurs raisons. Pour le détail d'implémentation, voir [.claude/](../.claude/) ; pour les fonctionnalités, [features.md](features.md).

---

## Vue d'ensemble

```text
Navigateur ──── session (cookie) ────► Controller/Web/     ──┐
                                                             ├──► Service/ ──► Repository/ ──► MariaDB
Client API ──── JWT (Bearer) ────────► State/ (API Platform) ┘                 │
                                                                               └──► var/storage/ (fichiers)
```

Deux portes d'entrée, une seule logique métier. L'interface web et l'API ne partagent ni authentification ni contrôleurs, mais délèguent aux **mêmes services**.

## Structure de `src/`

| Dossier       | Rôle                                                             |
|---------------|------------------------------------------------------------------|
| `Controller/` | HTTP uniquement — lit la requête, délègue, rend la réponse       |
| `Service/`    | Logique métier, testable sans HTTP ni base                       |
| `Repository/` | Accès aux données                                                |
| `State/`      | Processors et providers API Platform                             |
| `Security/`   | Authentification, ownership, contrôle d'accès aux partages       |
| `Interface/`  | Contrats — les services dépendent d'abstractions, pas de classes |
| `Entity/`     | Entités Doctrine                                                 |
| `Message/`    | Messages Symfony Messenger (traitement asynchrone)               |

Un contrôleur qui contient de la logique métier est un bug de conception : il devient intestable sans requête HTTP, et la logique n'est plus réutilisable entre le web et l'API.

## Authentification — deux firewalls

| Firewall | Portée               | Mécanisme                              |
|----------|----------------------|----------------------------------------|
| `web`    | `^/`                 | Session Symfony (`form_login`), cookie |
| `api`    | `^/api`              | JWT stateless (LexikJWT)               |
| `login`  | `/api/v1/auth/login` | Émission du JWT                        |

**Pourquoi deux.** Une balise `<img>` n'envoie jamais d'en-tête `Authorization` : servir une vignette à l'API depuis le HTML était impossible. D'où des routes web dédiées (`/gallery/{id}/thumbnail`) qui s'appuient sur la session.

Le front web appelle malgré tout l'API pour certaines actions : un **pont session → JWT** (`GET /web/token`) lui délivre un jeton, mis en cache 14 minutes côté navigateur.

> `access_control` est dupliqué entre `dev`/`prod` et l'environnement `test` : Symfony refuse de fusionner deux `access_control`. Toute règle ajoutée dans `config/packages/security.yaml` doit l'être aussi dans `config/packages/test/security.yaml`.

## Contrôle d'accès

Trois couches, du plus général au plus précis :

1. **`access_control`** — barrière large : `^/api` exige `ROLE_USER`, sauf les routes publiques listées explicitement (login, refresh, reset password, docs, `/p/` pour les partages par lien).
2. **`OwnershipChecker`** — la ressource appartient-elle à l'utilisateur ?
3. **Voters** — cas particuliers, notamment l'accès à un album partagé.

Un compte invité (`ACCOUNT_TYPE_GUEST`) est verrouillé par `GuestRestrictionChecker` : il consulte ce qu'on lui a partagé, il ne crée rien.

**Partage par lien** : le secret est entièrement porté par le couple `(selector, token)` dans l'URL — aucune session. Un token faux, un lien expiré ou révoqué renvoient tous **404, jamais 403** : confirmer l'existence d'un selector renseignerait un attaquant.

## Traitement asynchrone

L'extraction EXIF et la génération de vignette prennent plusieurs secondes sur une grande image. Les faire pendant l'upload ferait attendre l'utilisateur pour rien.

`MediaProcessMessage` part donc en file (transport Doctrine, table en base — pas de Redis requis sur un hébergement mutualisé). En test, le transport est `in-memory://` : pas de worker à lancer.

`MediaProcessor` est appelable **des deux façons** : en asynchrone par le handler, en synchrone quand le `Media` est requis immédiatement (import direct dans un album).

## Stockage

Les fichiers vivent sur le disque, jamais en base :

```text
var/storage/
├── 2026/07/{uuid}.NEF     ← fichiers d'origine, rangés par année/mois
├── thumbs/{uuid}.jpg      ← vignettes (référencées en base)
└── previews/{hash}.jpg    ← cache des previews RAW (dérivé, jetable)
```

Seul `thumbs/` est référencé en base (`Media::$thumbnailPath`). `previews/` est un cache dont le nom est **dérivé du chemin source** : pas de migration, pas de champ à maintenir, et un `rm -rf` suffit à le purger.

> Les fichiers sont stockés **en clair**. `APP_ENCRYPTION_KEY` existe dans la configuration mais n'est aujourd'hui injecté nulle part : le chiffrement au repos est prévu — laissé à l'appréciation de l'utilisateur — mais pas encore implémenté.

## Médias et fichiers RAW

Le sujet le plus dense du projet : trois artefacts par photo, un pipeline asynchrone, un cache, et deux pièges d'orientation qui ont chacun produit un vrai bug.

**À lire avant toute intervention sur les images** : [.claude/medias.md](../.claude/medias.md).

En résumé : GD ne décode pas les RAW, on extrait la preview JPEG embarquée par l'appareil ([package dédié](https://github.com/ronan-develop/raw-preview-extractor), publié séparément sous MIT). Une preview est stockée telle que le capteur l'a vue — la redresser est à la charge de l'application, **avant** de la redimensionner.

## Frontend

Pas de framework JS, pas de bundler.

- **AssetMapper** — les fichiers JS sont servis tels quels, versionnés par empreinte. Pas d'étape de build, pas de `node_modules` en production.
- **Stimulus** — des contrôleurs branchés par `data-controller` sur le HTML rendu par Twig.
- **Tailwind v4** — via `symfonycasts/tailwind-bundle`, sans PostCSS.

**Deux règles héritées d'un nettoyage** :

1. **Pas de `<script>` inline** dans les templates — un contrôleur Stimulus à la place.
2. **Pas de `style=""` inline** — un fichier CSS dédié par composant, importé dans `assets/styles/app.css`.

Ces règles ne sont pas cosmétiques : du JS inline ciblant un élément supprimé casse **en silence**, sans erreur de compilation ni test rouge. C'est exactement ainsi qu'un bouton « Importer » est resté mort après un refactor.

## Cache navigateur

Symfony répond par défaut en `max-age=0` : chaque scroll dans une galerie retéléchargeait les vignettes déjà vues.

`MediaCacheHeaders` centralise les en-têtes des routes servant des images. Une image est immuable — seule la suppression la rend obsolète, et la route répond alors 404.

- Médias authentifiés → `private` : un cache partagé ne doit pas les servir à un autre utilisateur.
- Partages par lien → `public` : le secret est dans l'URL, pas dans une session.
- Téléchargement → **aucun cache**.

## Tests

**TDD sans exception** : le test échoue d'abord. Un test écrit après coup valide ce que le code fait, pas ce qu'il devrait faire.

Une assertion doit **mordre** : préférer une vérification dimensionnelle (`320x480`) à une approximation (« plus haut que large ») qui passerait sur une image rabougrie.

| Type             | Emplacement                     | Sans base |
|------------------|---------------------------------|-----------|
| Unitaires        | `tests/Unit/`, `tests/Service/` | oui       |
| Fonctionnels web | `tests/Web/`                    | non       |
| API              | `tests/Api/`                    | non       |
| Frontend         | `assets/tests/` (Jest)          | oui       |

Les fixtures RAW ne sont pas versionnées (> 50 Mo) : les tests qui en dépendent se **skippent** proprement en leur absence — ils valident en local, ils ne protègent pas la CI.

## Base de données

**UUID v7 initialisé dans le constructeur**, jamais `#[ORM\GeneratedValue]` ni `?Uuid $id = null` — l'entité doit avoir une identité dès `new`, avant tout `flush()`. Voir [.claude/architecture.md](../.claude/architecture.md).

Les migrations sont générées par `make:migration` (diff automatique), jamais écrites à la main.

## Déploiement

Push sur `main` avec CI verte → déploiement automatique. Hébergement mutualisé o2switch : pas d'accès root, donc pas d'`apt install` — toute dépendance système est exclue par principe.

Détail dans [.claude/deploiement.md](../.claude/deploiement.md) et [.claude/cicd.md](../.claude/cicd.md).
