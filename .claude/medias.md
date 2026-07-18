# Pipeline média — vignettes, plein écran, RAW et cache

Comment une photo devient une vignette, ce qui est stocké où, et pourquoi les fichiers RAW ont un chemin à part.

---

## Les trois artefacts d'une photo

Une photo uploadée produit jusqu'à trois fichiers distincts. Les confondre est la première source de malentendu.

```text
DSC_0190.NEF (52,5 Mo)
   │
   ├── File (BDD) ──── path: "2026/07/xxx.NEF"  →  var/storage/2026/07/xxx.NEF
   │      ▲
   │      │ OneToOne
   │      │
   └── Media (BDD) ─── thumbnailPath: "thumbs/019f...jpg"
                              │
                              └→ var/storage/thumbs/019f...jpg
```

| Artefact | Emplacement | Généré | Poids (ex. NEF) |
|----------|-------------|--------|-----------------|
| Fichier original | `var/storage/{année}/{mois}/` | à l'upload, conservé tel quel | 52,5 Mo |
| Vignette (galerie) | `var/storage/thumbs/{uuid}.jpg` | **une fois**, pipeline async | 43 Ko |
| Preview (plein écran) | `var/storage/previews/{hash}.jpg` | **1ʳᵉ vue**, puis cachée | 1,05 Mo |

Seule la vignette est référencée en base (`Media::$thumbnailPath`). La preview ne l'est pas : son nom est dérivé du chemin source (voir *Cache des previews*).

---

## Le pipeline

```text
upload → CreateFileService → File
                               │
                               ▼
              mediaProcessor->supports(mimeType, nom) ?
                               │
                    ┌──────────┴──────────┐
                  true                  false
                    │                      │
       dispatch async (secours)      rien à faire
       + PendingMediaProcessingCollector
                    │
       kernel.terminate (juste après la réponse HTTP)
                    │
                    ▼
                    MediaProcessor::process()
                               │
                    resolveMediaType(mimeType, nom)
                               │
                    ┌──────────┴──────────┐
                 photo                  video / null
                    │                      │
         ExifService::extract()         Media sans EXIF
         ThumbnailService::generate()   ni vignette
                    │
                 Media
```

`supports()` est la **seule source de vérité** pour décider si un fichier mérite un traitement (image/video par mimeType, ou RAW reconnu par extension quand le mimeType ne dit rien) — les deux contrôleurs d'upload (API et web) l'utilisent, pour ne jamais dupliquer cette logique et risquer de l'oublier sur un chemin (déjà arrivé : les RAW en `application/octet-stream` étaient silencieusement ignorés avant).

`MediaProcessor::process()` est appelé :
- **immédiatement après la réponse HTTP** (`kernel.terminate`, via `PendingMediaProcessingCollector` + `ProcessPendingMediaListener`) — chemin normal depuis #251, latence perçue nulle
- **en asynchrone** (Messenger, cron) en secours si le traitement immédiat échoue — idempotent, un double traitement ne fait rien de plus qu'un no-op
- **en synchrone** quand le Media est requis immédiatement pour une autre raison (import direct dans un album, `AlbumImportService`)

`ThumbnailService::generate()` tente d'abord `exif_thumbnail()` (miniature déjà embarquée dans l'IFD1 EXIF de la plupart des JPEG) avant de décoder l'image pleine résolution avec GD — décoder une image haute résolution juste pour en tirer une vignette de 320px peut à lui seul saturer la mémoire du worker.

---

## Fichiers RAW (CR2, CR3, NEF, ARW, DNG)

GD ne sait pas décoder un RAW : `imagecreatefromstring()` échoue silencieusement. On extrait donc la **preview JPEG que l'appareil embarque déjà** dans le fichier, via [`ronanlenouvel/raw-preview-extractor`](https://github.com/ronan-develop/raw-preview-extractor).

### Détection

Les navigateurs n'ont pas de mimeType pour les RAW et envoient `application/octet-stream`. `MediaProcessor::resolveMediaType()` retombe donc sur **l'extension** — sans quoi aucun `Media` n'était créé et la vignette n'était jamais tentée.

La liste `RAW_EXTENSIONS` est volontairement alignée sur les formats que le package sait lire : créer un `Media` pour un RAW dont on ne peut pas extraire de preview ne produirait qu'une vignette vide.

### Enregistrement du bundle

Le package est publié en `type: library` (réutilisable hors Symfony) : **Flex ne l'enregistre pas automatiquement**, contrairement à un `type: symfony-bundle`. Le bundle est déclaré à la main dans `config/bundles.php`.

### Orientation — le piège

Une preview est stockée **telle que le capteur l'a vue** : l'appareil enregistre la rotation à appliquer plutôt que de l'appliquer lui-même. Une photo prise en portrait ressort donc couchée.

Le package s'interdit de la redresser (il évite GD par design) : **c'est à l'application de le faire**, dans `ThumbnailService` comme dans `MediaFullResponseFactory`.

Deux règles à ne pas perdre :

1. **Le signe.** `imagerotate()` tourne en anti-horaire là où l'EXIF compte en horaire → `imagerotate($img, -$orientation->degrees())`. Un `+` sort l'image à 180° du bon sens.
2. **L'ordre.** Redresser **avant** de redimensionner. Contraindre la largeur à 320 px sur une image encore couchée (8256×5504 en `Rotate90`) donne une vignette de **213 px** de large une fois tournée — droite, mais plus petite que les photos paysage.

`Orientation::isMirrored()` couvre quatre des huit valeurs EXIF. Rares, mais les ignorer afficherait certaines images en miroir.

---

## Affichage plein écran

`MediaFullResponseFactory` sert la lightbox, le diaporama et les partages publics — les deux contrôleurs concernés avaient la même logique dupliquée.

| Type de fichier | Réponse |
|-----------------|---------|
| Image classique | `BinaryFileResponse` — streamée du disque, jamais en mémoire |
| RAW | Preview extraite, redressée, redimensionnée, mise en cache |
| RAW sans preview | Le fichier d'origine, tel quel (dégradation gracieuse) |

### Pourquoi redimensionner à 2160 px

Une preview RAW fait 8256 px de haut, un écran QHD en affiche 1440 : **le navigateur la réduisait déjà**. La servir à 2160 px reste 1,5× plus dense qu'un tel écran — de la marge pour le zoom — sans les 6 Mo inutiles.

Mesuré sur un NEF réel : **1,05 Mo** servi au lieu de 52,5 Mo, soit −98 %.

Une preview déjà droite et de taille raisonnable n'est **ni réencodée ni redimensionnée** : la dégrader sans raison n'aurait aucun sens.

---

## Cache des previews

Préparer une preview coûte ~1 s (décodage, rotation, rééchantillonnage de 45 Mpx). Sans cache, un diaporama repaierait ce prix à chaque photo et à chaque passage.

`RawPreviewCache` écrit dans `var/storage/previews/`. Mesuré sur un NEF réel :

| | Temps |
|---|---|
| 1ʳᵉ vue (extraction + rotation + resize) | 1138 ms |
| 2ᵉ vue (cache disque) | **0,6 ms** |

### Le nom du cache est dérivé, pas stocké

`hash('xxh128', $cheminSource)` plutôt qu'une colonne en base :

- pas de migration Doctrine ni de champ à maintenir ;
- le cache reste **jetable** — `rm -rf var/storage/previews/` et tout se régénère ;
- le hash aplatit le chemin (qui contient des slashes) en un nom de fichier plat, ce qui écarte au passage toute traversée de répertoire.

### Invalidation

`MediaDeletionService::delete()` appelle `RawPreviewCache::evict()`. Sans ça, chaque suppression laisserait ~1 Mo orphelin sur le disque. L'appel est sans effet pour un JPEG, qui n'a jamais de preview en cache.

---

## Cache navigateur

Symfony répond par défaut en `max-age=0, must-revalidate`. Appliqué à une image, ce défaut faisait **retélécharger chaque vignette à chaque scroll** : une galerie de 200 photos consultée cinq fois émettait 1000 requêtes là où 200 suffisent (−80 % de requêtes et de trafic).

Une image est pourtant immuable : seule la suppression du média la rend obsolète, et la route répond alors 404 — le cache ne peut donc pas servir un contenu périmé.

`MediaCacheHeaders` centralise ces en-têtes. Quatre routes servent des images ; un oubli sur l'une d'elles passerait inaperçu.

| Route | Cache |
|-------|-------|
| `media_thumbnail` (API) | `private, max-age=3600` |
| `app_media_thumbnail` (galerie) | `private, max-age=3600` |
| `app_media_full` (lightbox) | `private, max-age=3600` |
| `app_public_share_media_*` | `public, max-age=3600` |
| `app_file_download` | **aucun** — un téléchargement n'a pas à rester en cache |

`shared: true` est réservé aux liens publics : leur secret est dans l'URL, pas dans une session. Pour tout média authentifié, `private` empêche un cache partagé de le servir à un autre utilisateur.

> `must-revalidate` persiste sur `BinaryFileResponse` (Symfony le repose). Inoffensif : il ne joue qu'**après** expiration du `max-age`, et c'est même souhaitable — un média supprimé entre-temps ne sera pas servi périmé.

---

## Choix de cache — ce qui a été écarté, et pourquoi

Mesuré sur la machine de dev et la MariaDB réelle, pour une preview de 1 Mo :

| Solution | Lecture | Verdict |
|----------|---------|---------|
| **Cache disque** (retenu) | **0,40 ms** | Un fichier est un fichier |
| Cache applicatif Symfony | 1,63 ms — ×4 | Même disque + sérialisation d'un blob |
| Cache BDD (`LONGBLOB`) | 4,28 ms — ×10,7 (158 ms en écriture) | Buffer pool, dumps, quota BDD |

o2switch propose **Redis et Memcached** (instances privées cPanel, offres Unique/Grow/Cloud/Pro) — utiles pour de petites données volatiles (sessions, métadonnées Doctrine), **pas pour des blobs de 1 Mo** en RAM mutualisée.

Le serveur tourne sous **LiteSpeed** (`server: o2switch-PowerBoost-v3`), mais LSCache ne met en cache que des pages HTML publiques : les médias sont privés et authentifiés, il ne les cachera pas — à raison.

---

## Fichiers PDF

Pas de `Media` créé (`MediaProcessor::resolveMediaType()` retourne `null` pour `application/pdf`) — pas de vignette, pas d'EXIF. Un PDF reste un `File` seul.

### Visualisation (#241)

La plupart des navigateurs affichent nativement un PDF (pagination, zoom, recherche texte) si le serveur répond en `Content-Disposition: inline` plutôt qu'`attachment`. `FileWebController::view()` (route `app_file_view`) sert exactement le même fichier que `download()` (même autorisation, factorisée dans `buildFileResponse()`), seule la disposition change.

**Aucune librairie JS de rendu PDF (pas de PDF.js)** : le bouton "Visualiser" de `FileCard.html.twig` ouvre une modale avec une simple `<iframe src="…/view">` — c'est le lecteur du navigateur qui fait tout le travail.

### Vignette de première page — non fait

Idée explorée mais pas implémentée : rasteriser la page 1 via Ghostscript (`gs`, présent sur o2switch, `Process`/`shell_exec` non bloqués) — même esprit que l'extraction de preview RAW (extraire quelque chose de déjà là plutôt que tout re-décoder). Nécessiterait une dégradation gracieuse identique au pipeline RAW si `gs` est absent/échoue. Voir #241.

---

## Points ouverts

- **EXIF des RAW** : `exif_read_data()` ne lit pas ces conteneurs. Date de prise de vue, modèle d'appareil et GPS restent vides pour ces photos (la vignette, elle, fonctionne). Deux pistes : lire les EXIF de la preview extraite (simple, métadonnées appauvries), ou exposer les tags TIFF depuis le package (plus riche).
- **Vignette d'un RAW** : ~2,9 s sous GD pour redimensionner du 8256×5504, contre 23 ms pour l'extraction. Acceptable car le pipeline est asynchrone ; un redimensionnement en deux passes réduirait le coût.
- **Fixtures RAW** : non versionnées (> 50 Mo, exclues par `.gitignore`). `ThumbnailServiceRealRawTest` se skippe proprement en leur absence — il valide en local, il ne protège pas la CI.
