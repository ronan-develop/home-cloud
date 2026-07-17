# Plan — Rendre HomeCloud testable par de vrais utilisateurs

**Objectif** : lever les trois blocages qui empêchent un testeur d'utiliser les deux fonctionnalités phares (galerie, partage).

**Pour l'exécutant** : chaque étape est autonome. Les commandes sont à copier telles quelles. Les chemins, noms de fichiers et valeurs sont donnés — **ne rien deviner, ne rien inventer**. En cas de doute, lire les fichiers cités avant d'agir.

---

## Ce qui est DÉJÀ fait — ne pas y toucher

Vérifié dans le code le 2026-07-17. Ces points sont **corrects**, toute "correction" serait une régression :

| Élément | État | Preuve |
|---------|------|--------|
| Dispatch du message à l'upload | ✅ fonctionne | `src/Controller/Api/FileUploadController.php:79` |
| Branchement du contrôleur d'upload | ✅ fonctionne | `src/ApiResource/FileOutput.php:49` (`controller: FileUploadController::class`) |
| Handler asynchrone | ✅ fonctionne | `src/Handler/MediaProcessHandler.php` |
| Routage `MediaProcessMessage` → `async` | ✅ configuré | `config/packages/messenger.yaml` |
| Les 9 failles de l'audit sécurité | ✅ corrigées | `access_control` présent, 19 contrôles CSRF, CSP active |

> **Piège identifié.** `src/Service/CreateFileService.php:106` contient
> `// TODO: Implement media dispatcher when needed`.
> Ce commentaire est **périmé et trompeur** : le dispatch est fait par
> `FileUploadController`, pas par ce service. L'étape 4 le supprime.
> Ne pas ajouter de dispatch dans `CreateFileService` — ce serait un doublon.

---

## Le vrai problème

Le pipeline est complet, mais **rien ne consomme la file**. Résultat mesuré en dev :

```text
20 messages en attente dans messenger_messages, le plus ancien du 2026-03-12
```

Concrètement, pour un testeur : il uploade une photo → elle apparaît dans *Mes fichiers* → **jamais dans la Galerie**. Ni vignette, ni EXIF.

---

## Étape 1 — Worker Messenger en production

**Problème** : aucun processus ne consomme la file `async`. Le script `bin/deploy.sh` ne lance pas de worker (vérifié : il ne fait que `cache:clear`, `migrate`, `asset-map:compile`).

**Solution retenue** : une tâche cron cPanel toutes les 5 minutes, avec `flock` pour éviter les processus concurrents. C'est l'approche documentée par o2switch — pas de Supervisor sur un mutualisé.

### 1.1 — Créer la tâche cron

Interface cPanel o2switch → **Tâches Cron** → ajouter :

| Champ | Valeur |
|-------|--------|
| Intervalle | `*/5 * * * *` (toutes les 5 minutes) |

Commande (remplacer `<prenom>` par l'instance concernée) :

```bash
flock -n /home9/ron2cuba/.messenger-<prenom>.lock /usr/local/bin/php /home9/ron2cuba/<prenom>.lenouvel.me/bin/console messenger:consume async --time-limit=290 --memory-limit=128M --env=prod >> /home9/ron2cuba/<prenom>.lenouvel.me/var/log/messenger.log 2>&1
```

**Pourquoi ces valeurs — ne pas les changer sans raison** :

- `flock -n` : si un worker tourne déjà, la nouvelle tentative ne fait rien. Sans lui, on accumulerait un processus toutes les 5 minutes.
- `--time-limit=290` : le worker meurt avant le prochain cron (300 s). Sans lui, `flock` bloquerait tout redémarrage.
- `--memory-limit=128M` : PHP fuit en processus long ; le worker redémarre proprement avant de saturer.
- `>> ... 2>&1` : sans redirection, les erreurs du worker sont invisibles.

### 1.2 — Vérifier que le worker tourne

Après 5 minutes, en SSH :

```bash
cd /home9/ron2cuba/<prenom>.lenouvel.me
php bin/console messenger:stats --env=prod     # doit afficher 0 (ou décroître)
tail -20 var/log/messenger.log                 # aucune erreur attendue
```

**Critère de réussite** : uploader une photo depuis l'interface → elle apparaît dans la Galerie avec sa vignette en moins de 5 minutes.

### 1.3 — Vider la file de dev (optionnel, local)

Les 20 messages en attente référencent des fichiers probablement supprimés. Le handler gère ce cas (`MediaProcessHandler.php:32` — `if ($file === null) return;`), ils seront donc consommés sans effet.

```bash
php bin/console messenger:consume async --time-limit=30    # en local
```

---

## Étape 2 — Envoi des emails en production

**Problème** : `.env` contient `MAILER_DSN=null://null`. Les emails sont générés mais jamais envoyés. Un invité créé ne reçoit pas son lien d'activation → **il ne peut pas se connecter** → tout le flux de partage est inopérant.

**À savoir** : `.env.local` (local, non versionné) contient déjà le DSN o2switch en commentaire, avec la note « injoignable depuis localhost (port) ». Le port est bloqué **en local**, pas sur le serveur.

### 2.1 — Renseigner le DSN sur le serveur

En SSH, éditer `/home9/ron2cuba/<prenom>.lenouvel.me/.env.local` :

```bash
MAILER_DSN=smtp://<user>%40lenouvel.me:<password>@lenouvel.me:465
```

**Règles** :

- `@` dans l'identifiant s'encode `%40`, les caractères spéciaux du mot de passe aussi (`$` → `%24`).
- Ce fichier n'est **jamais** versionné (`.gitignore`).
- Ne pas modifier `.env` (versionné) : `null://null` doit y rester comme valeur par défaut inoffensive.

### 2.2 — Vérifier

```bash
cd /home9/ron2cuba/<prenom>.lenouvel.me
php bin/console cache:clear --env=prod
```

Puis, depuis l'interface : créer un invité sur `/invites` avec une adresse réelle qu'on contrôle. **Critère de réussite** : l'email d'activation arrive, et son lien permet de définir un mot de passe.

Si rien n'arrive, consulter `var/log/prod.log` (chercher `mailer` ou `Transport`).

---

## Étape 3 — Extensions PHP déclarées

**Problème** : `composer.json` ne requiert que `ext-ctype` et `ext-iconv`. Or `gd` et `exif` sont indispensables :

- `gd` → `src/Service/ThumbnailService.php`, `src/Service/MediaFullResponseFactory.php`
- `exif` → `src/Service/ExifService.php`

Sur un serveur sans ces extensions, `composer install` passerait, puis les vignettes échoueraient **silencieusement** (`ThumbnailService::generate()` retourne `null` si `imagecreatefromstring` n'existe pas).

### 3.1 — Déclarer les extensions

Dans `composer.json`, section `require`, ajouter à côté de `ext-ctype` :

```json
"ext-gd": "*",
"ext-exif": "*",
```

### 3.2 — Vérifier

```bash
composer update --lock          # met à jour composer.lock sans toucher aux versions
php -m | grep -E "^(gd|exif)$"  # doit afficher les deux
composer install --dry-run      # ne doit signaler aucune extension manquante
```

**Critère de réussite** : `composer install` passe en local et en CI (la CI a déjà ces extensions, le job `php` doit rester vert).

---

## Étape 4 — Nettoyer les commentaires trompeurs

**Problème** : deux commentaires mentent sur le code, ce qui fera perdre du temps au prochain lecteur — comme ils m'en ont fait perdre.

### 4.1 — `src/Service/CreateFileService.php`

Ligne ~106, supprimer ces deux lignes :

```php
// 8. Dispatch async: media processing (image EXIF, thumbnail, etc.)
// TODO: Implement media dispatcher when needed
```

**Pourquoi** : le dispatch est fait par `FileUploadController:79`, qui appelle ce service. Ce TODO laisse croire à un manque inexistant.

Ajuster aussi le docblock de la classe (ligne ~22) :

```php
 * Workflow: validate → store → persist → dispatch async
```

devient :

```php
 * Workflow: validate → store → persist
 * (le dispatch du traitement média est fait par l'appelant, FileUploadController)
```

### 4.2 — `src/Service/ThumbnailService.php`

Ligne ~41, le docblock prétend :

```php
 * @param string $absolutePath Chemin absolu de l'image source (chiffrée sur disque)
```

Or les fichiers sont **en clair** — la ligne 23 du même fichier le dit. Remplacer par :

```php
 * @param string $absolutePath Chemin absolu de l'image source
```

### 4.3 — Vérifier

```bash
grep -rn "TODO: Implement media dispatcher" src/     # ne doit rien renvoyer
grep -n "chiffrée sur disque" src/Service/ThumbnailService.php    # ne doit rien renvoyer
rm -rf var/cache/test && ./vendor/bin/phpunit
```

**Critère de réussite** : suite verte (738 tests au 2026-07-17). Aucun test ne dépend de ces commentaires — c'est une modification sans risque.

---

## Étape 5 — Documenter le worker

**Problème** : la tâche cron de l'étape 1 est invisible dans le repo. Un futur déploiement sur une nouvelle instance l'oublierait, et le bug (« pas de vignette ») serait rejoué à l'identique.

### 5.1 — Ajouter à `.claude/deploiement.md`

Après la section « Chemins importants sur o2switch », insérer :

```markdown
## Worker Messenger — obligatoire

Le traitement des médias (EXIF, vignettes) part en file asynchrone. **Sans worker,
aucune vignette n'est générée** : les fichiers s'uploadent mais n'apparaissent
jamais dans la Galerie.

Supervisor n'existe pas sur un mutualisé : on passe par une tâche cron cPanel
toutes les 5 minutes.

| Champ      | Valeur          |
|------------|-----------------|
| Intervalle | `*/5 * * * *`   |

\```bash
flock -n /home9/ron2cuba/.messenger-<prenom>.lock /usr/local/bin/php /home9/ron2cuba/<prenom>.lenouvel.me/bin/console messenger:consume async --time-limit=290 --memory-limit=128M --env=prod >> /home9/ron2cuba/<prenom>.lenouvel.me/var/log/messenger.log 2>&1
\```

- `flock -n` empêche l'accumulation de workers concurrents.
- `--time-limit=290` : le worker meurt avant le cron suivant (300 s).
- `--memory-limit=128M` : redémarrage propre avant que PHP ne fuie.

Diagnostic :

\```bash
php bin/console messenger:stats --env=prod   # messages en attente
tail -20 var/log/messenger.log               # erreurs du worker
\```
```

### 5.2 — Ajouter à la ligne de diagnostic

Dans le tableau « Diagnostic — erreurs fréquentes » du même fichier :

```markdown
| Pas de vignette dans la Galerie | Worker Messenger absent | Vérifier la tâche cron (voir ci-dessus) |
```

---

## Ordre d'exécution

Les étapes 1 et 2 sont **indépendantes** et peuvent se faire dans n'importe quel ordre. Les étapes 3, 4, 5 sont du code/doc et passent par une PR.

```text
Étape 1 (cron)      ─┐
                     ├─► serveur, pas de PR — à faire en premier, c'est le bloquant
Étape 2 (mailer)    ─┘

Étape 3 (composer)  ─┐
Étape 4 (comments)   ├─► une seule PR
Étape 5 (doc)       ─┘
```

---

## Conventions de travail — non négociables

Rappel de `CLAUDE.md`, à respecter sans exception :

- **Jamais de commit sur `main`** — créer une branche d'abord.
- **Commits atomiques** — jamais `git add .` en bloc.
- **Confirmation obligatoire** avant `git push`, merge, ouverture de PR.
- **TDD** : pour toute modification de comportement, le test échoue d'abord.
  Les étapes 3, 4, 5 ne changent aucun comportement (config, commentaires, doc) :
  aucun nouveau test n'est requis, mais la suite doit rester verte.
- **Ne jamais commiter de secrets** — le `MAILER_DSN` de l'étape 2 va dans
  `.env.local` **sur le serveur**, jamais dans le repo.

---

## Définition de « prêt pour les testeurs »

Les cinq étapes faites, un testeur doit pouvoir enchaîner sans blocage :

1. Se connecter.
2. Uploader une photo (JPEG **et** NEF) → elle apparaît dans la Galerie avec sa vignette (< 5 min).
3. Ouvrir la photo en plein écran, lancer le diaporama.
4. Créer un album, y importer des photos.
5. Partager l'album avec une adresse email inconnue → l'invité **reçoit** son email,
   active son compte, et voit l'album partagé.
6. Créer un lien public → l'ouvrir en navigation privée.

Si un seul de ces points échoue, le test utilisateur produira du bruit plutôt que du signal.

---

## Hors périmètre — ne pas traiter ici

Ces points sont réels mais **n'empêchent pas** un test utilisateur. Ils sont consignés
dans `.github/avancement.md` (points 7 à 11) :

- EXIF des RAW non lus (date/appareil/GPS vides — la vignette fonctionne).
- Chiffrement au repos non implémenté.
- PWA annoncée dans `CLAUDE.md` mais inexistante.
- 141 notices PHPUnit.
- Redimensionnement GD à ~2,9 s sur les grandes previews.
