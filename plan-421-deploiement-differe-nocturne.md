# Plan d'implémentation — #421 Déploiement différé nocturne autonome

> Document de planification destiné à être exécuté pas à pas par un agent implémenteur.
> Ticket : https://github.com/ronan-develop/home-cloud/issues/421
> Résout également le blocage historique de #288 (déploiement automatisé).

---

## 1. Objectif

**Zéro commande à lancer.** Un merge sur `main` doit aboutir à un déploiement de toutes les instances **la nuit suivante**, sans que le poste local soit allumé, sans intervention humaine.

État actuel : `bin/deploy-all.sh` est lancé à la main depuis le poste local, en pleine journée, et déploie immédiatement les 7 instances — ce qui a déjà impacté un utilisateur en cours d'usage.

---

## 2. Décisions d'architecture

### 2.1 Pourquoi GitHub ne peut pas pousser vers les instances — tranché par l'expérience

`.claude/cicd.md` et `.claude/deploiement.md` affirment que l'automatisation est impossible car « o2switch bloque les IPs des runners GitHub Actions ». Cette affirmation méritait d'être vérifiée : elle ne vaut a priori que pour le **SSH (port 22)**, protégé par le firewall cPanel, alors que `public/deploy.php` est un **webhook HTTPS** (port 443) avec HMAC-SHA256, hors whitelist SSH.

Deux campagnes de tests ont été menées le 2026-09-12 pour trancher.

#### Test 1 — depuis le poste local (IP résidentielle française) : ✅ tout passe

| Test | Résultat |
|------|----------|
| `GET https://ronan.lenouvel.me/deploy.php` | **405** en 0,11 s |
| `GET https://yannick.lenouvel.me/deploy.php` | **405** en 0,12 s |
| `POST` sans signature | **500** « Webhook secret not configured » |
| `POST` avec `User-Agent: GitHub-Hookshot/…` | **500** identique |
| `POST` **signé HMAC**, branche non-`main` | **200** « Skipped: not main branch » |
| `POST` signé avec une **mauvaise** signature | **401** « Invalid signature » |

Depuis cette IP, le webhook est pleinement fonctionnel — la chaîne cryptographique complète a été validée.

> `DEPLOY_WEBHOOK_SECRET` a été généré et posé sur les 7 instances + dans les secrets GitHub au cours de ces tests. **Cette configuration n'est plus utile** à l'architecture retenue (§2.4) ; voir §9 pour le nettoyage.

#### Test 2 — depuis un vrai runner GitHub (IP `20.127.215.180`, Azure) : ❌ ÉCHEC

Workflow `probe-o2switch.yml`, exécuté sur `ubuntu-latest` :

| Requête depuis le runner | Résultat |
|--------------------------|----------|
| `GET /deploy.php` (HTTP/2) | `curl (92) PROTOCOL_ERROR`, code `000` |
| `POST /deploy.php` non signé | `PROTOCOL_ERROR`, code `000` |
| `POST /deploy.php` signé HMAC | `PROTOCOL_ERROR`, code `000` |
| `GET /deploy.php` **en HTTP/1.1 forcé** | `PROTOCOL_ERROR`, code `000` |
| `GET /deploy.php` avec **User-Agent navigateur** | `PROTOCOL_ERROR`, code `000` |
| **`GET /` (page d'accueil)** | **`302` — succès** |

**Diagnostic** : ce n'est **pas** un blocage d'IP ni un géo-filtrage. La page d'accueil répond normalement au même runner, sur la même IP, au même instant. Seule l'URL `/deploy.php` voit sa connexion coupée, quel que soit le protocole (HTTP/2 comme HTTP/1.1) et le User-Agent.

C'est un **WAF applicatif** (ModSecurity / LiteSpeed) qui cible cette URL. Depuis une IP résidentielle française les mêmes requêtes passent (405 / 500 / 401 obtenus), depuis Azure la connexion est rompue au niveau du stream TLS.

**Conséquence : toute architecture où GitHub pousse vers les instances est INVALIDE.** Ne pas réessayer.

> Pistes écartées volontairement, pour ne pas les re-explorer :
> - Renommer `deploy.php` en une URL moins typée contournerait sans doute la règle WAF, mais c'est de la sécurité par l'obscurité, fragile à la prochaine mise à jour des règles o2switch.
> - Demander au support o2switch une exception WAF est possible, mais crée une dépendance externe pour un gain nul face à la solution retenue, plus simple et entièrement sous contrôle.
> - Un self-hosted runner résoudrait le problème mais suppose une machine allumée en permanence — contraire à l'objectif (poste éteint).

### 2.2 Pas de base de données

- Chaque instance a sa base isolée (`ron2cuba_<prenom>`) — un état partagé imposerait une 8ᵉ base « centrale » pour y stocker quelques lignes.
- Aucune migration Doctrine, aucune entité.
- Un fichier reste inspectable et réparable à la main (`cat`, `rm`) en SSH — précieux sur un mutualisé où le debug se fait en aveugle.

**Réponse explicite à la question posée** : non, il ne faut pas créer de base de données sur `lenouvel.me`.

### 2.3 ✅ ARCHITECTURE RETENUE — le serveur tire depuis GitHub

L'autonomie est entièrement préservée : seul le déclencheur change. C'est **le serveur qui va chercher** le travail, au lieu de GitHub qui le pousse. Aucune connexion entrante n'est requise — uniquement des requêtes **sortantes** du serveur vers GitHub, que rien ne bloque (le `git pull` de `deploy-all.sh` en est la preuve quotidienne).

#### Le CSS Tailwind — conséquence à assumer

Le CSS ne peut plus venir du runner (il ne peut plus rien nous transmettre). **Décision : la CI committe `var/tailwind/app.built.css` sur `main`.**

- retirer `var/tailwind/app.built.css` du `.gitignore` ;
- ajouter à `ci.yml` (job `php`, sur `push` vers `main` uniquement) une étape qui, après `tailwind:build --minify`, commit le fichier s'il a changé ;
- le serveur le récupère alors par simple `git checkout`, sans jamais builder Tailwind.

> **Piège à éviter** : cette étape ne doit tourner **que** sur `push: main`, jamais sur `pull_request` — sinon la CI committerait sur les branches de PR. Et le commit doit porter `[skip ci]` dans son message, sous peine de boucle infinie (commit → CI → commit → …).

#### Simplification majeure permise par ce choix

Sans webhook, **la pile devient inutile**. Personne n'a plus besoin d'écrire une entrée en journée : le cron nocturne peut lire lui-même l'état de `main` au moment où il s'exécute.

Ce qui disparaît du périmètre par rapport à la version initiale du plan :

- ❌ `bin/deploy-queue-lib.sh` — plus de pile à gérer
- ❌ La réécriture de `public/deploy.php` — le webhook n'est plus utilisé du tout
- ❌ `.github/workflows/deploy.yml` et `notify-instances.sh`
- ❌ Le secret HMAC partagé, l'encodage base64 du CSS, l'écriture atomique du JSON

Ce qui reste, et suffit : **un seul fichier d'état par instance**, `.deployed-sha`, contenant le SHA actuellement déployé. Le cron compare, déploie si différent, met à jour.

### 2.4 Flux cible

```text
  Merge PR sur main
        │
        ▼
  CI (ci.yml) — tests + build Tailwind, commit du CSS sur main
        │
        ▼   (plus rien ne se passe en journée — aucun user impacté)
        │
  ────────── la nuit, cron cPanel, une instance à la fois ──────────
        │
        ▼
  bin/deploy-nightly.sh <prenom>
        │  git fetch origin main
        │  SHA distant == contenu de .deployed-sha ?  → oui : ne rien faire, sortie 0
        │                                              → non : déployer
        │  git checkout <sha>
        │  composer install, migrations, cache, assets
        │  écrit .deployed-sha
        ▼
  2h45 : email récapitulatif à ronan@lenouvel.me
```

Point essentiel : **rien ne s'exécute en journée**. La CI ne touche qu'au dépôt GitHub, jamais aux instances. C'est ce qui garantit qu'aucun utilisateur n'est impacté.

> **`ronan.lenouvel.me` garde son traitement à part** : comme aucun autre utilisateur n'y est actif, son cron peut tourner plus tôt (ex. `0 1`) pour servir de canari — si le déploiement casse sur `ronan`, tu le vois avant que les 6 autres ne partent à `2h05`. Le script étant strictement identique partout, cela n'ajoute aucun code, seulement un horaire différent.

---

## 3. Décisions tranchées (ne pas re-débattre)

| Sujet | Décision |
|-------|----------|
| Déclencheur | **Cron nocturne sur chaque instance**, qui compare `main` à l'état déployé |
| Transport | **Sortant uniquement** : `git fetch` du serveur vers GitHub. Aucune connexion entrante, aucun webhook, aucun SSH depuis GitHub |
| `public/deploy.php` | **Hors périmètre** — non utilisé, non modifié. Voir §9 pour la décision de le supprimer ou non |
| Build Tailwind | Par la CI, qui **committe** `var/tailwind/app.built.css` sur `main` |
| État déployé | Fichier `.deployed-sha` à la racine de chaque instance |
| Pile / file d'attente | **Aucune** — le cron lit `main` au moment de s'exécuter |
| Base de données | **Aucune** — ni entité, ni migration |
| `ronan.lenouvel.me` | Cron plus tôt (`0 1`) = canari ; script identique aux autres |
| 6 autres instances | `2h05` → `2h30`, étalées de 5 min (contrainte LVE) |
| Bypass urgence | `bash bin/deploy-all.sh --now` depuis le poste local (inchangé) |
| Emails | Un seul récapitulatif à `2h45`, à `ronan@lenouvel.me` |
| Email « mis en pile » | **Supprimé** — sans pile, il n'a plus d'objet |
| Interface admin | Hors périmètre |
| Échec nocturne | `.deployed-sha` non mis à jour → nouvelle tentative la nuit suivante, signalée dans l'email |

---

## 4. État déployé — format

Un fichier par instance, à la racine du projet : `/home9/ron2cuba/<prenom>.lenouvel.me/.deployed-sha`

```text
e2e7c11a9f3c4d5e6f7890abcdef1234567890ab
```

Une seule ligne, le SHA complet. Rien d'autre.

- **À ajouter au `.gitignore`** — c'est un état local à l'instance, il ne doit jamais être committé.
- **Absent** (première exécution) → traiter comme « rien n'est déployé », donc déployer.
- **Mis à jour uniquement en cas de succès complet.** Un échec le laisse inchangé, ce qui provoque une nouvelle tentative la nuit suivante — le comportement voulu.

Le journal des tentatives va dans `var/log/deploy-nightly.log` (rotation naturelle par le serveur, non géré ici).

---

## 5. Livrables

| # | Livrable | Nature |
|---|----------|--------|
| 1 | `bin/deploy-nightly.sh` | Script exécuté par le cron sur chaque instance |
| 2 | `ci.yml` (modification) | Commit du CSS Tailwind sur `main` |
| 3 | `.gitignore` (modification) | Retirer `var/tailwind/app.built.css`, ajouter `.deployed-sha` |
| 4 | `src/Service/DeployNotificationMailer.php` + interface | Email récapitulatif |
| 5 | `src/Command/DeployQueueNotifyCommand.php` | Commande console appelée par le cron de `2h45` |
| 6 | `templates/emails/deploy_report.html.twig` | Template de l'email |
| 7 | `bin/deploy-all.sh` (modification) | Ajout du flag `--now`, filet de secours manuel |
| 8 | Documentation | `.claude/deploiement.md`, `.claude/cicd.md`, `.github/avancement.md` |

---

## 6. Détail des livrables

### 6.1 `bin/deploy-nightly.sh`

Cœur du chantier. Exécuté sur le serveur : `bash bin/deploy-nightly.sh <prenom>`.

```text
 1. cd vers le dossier de l'instance, mkdir -p var/log
 2. git fetch origin main
 3. REMOTE=$(git rev-parse origin/main)
    CURRENT=$(cat .deployed-sha 2>/dev/null || echo "")
 4. si REMOTE == CURRENT → log "à jour", écrire la ligne "skipped" au rapport, sortie 0
 5. sinon, exécuter dans l'ordre, CHAQUE ÉTAPE DANS SON PROPRE SOUS-SHELL :
      git checkout --force <REMOTE>
      composer install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts
      bash bin/install-ffmpeg.sh || true
      php bin/console cache:clear --env=prod
      php bin/console assets:install public --env=prod
      php bin/console importmap:install --env=prod
      php bin/console doctrine:migrations:migrate --no-interaction --env=prod
      php bin/console asset-map:compile
      echo "<!-- Deployed: <date> -->" > templates/deploy-info.html.twig
 6. succès → echo "$REMOTE" > .deployed-sha, ligne "ok" au rapport, sortie 0
    échec  → .deployed-sha INCHANGÉ, ligne "failed|<étape>" au rapport, sortie 1
```

Points de vigilance :

- **Un sous-shell par étape** : le compte tourne dans un LVE CloudLinux à quota mémoire partagé. Enchaîner les `bin/console` dans un seul process a déjà provoqué des `Killed` sans trace (incident 2026-07-23). C'est la raison d'être de `run_step` dans `deploy-all.sh` — reprendre le même principe.
- **Arrêt immédiat à la première étape en échec** : ne jamais enchaîner (`set -e` + chaînage `&&`). Déployer à moitié est pire que ne pas déployer.
- **`git checkout --force`** : le serveur peut avoir des fichiers modifiés (`templates/deploy-info.html.twig` est réécrit à chaque déploiement, `var/tailwind/app.built.css` sera désormais suivi). Sans `--force`, le checkout échoue.
- **Pas de `tailwind:build`** : le CSS arrive par git.
- **`mkdir -p var/log` en tout premier** : le cron redirige vers `var/log/deploy-nightly.log` ; si le dossier n'existe pas, la redirection échoue et **la commande ne s'exécute jamais**, sans la moindre erreur visible (piège déjà vécu avec le worker Messenger).
- Fichier de rapport partagé : `/home9/ron2cuba/.deploy-report-$(date +%Y-%m-%d).log`, une ligne par instance, format `<prenom>|<ok|failed|skipped>|<étape ou ->|<sha court>`.

### 6.2 Modification de `ci.yml` — commit du CSS

Dans le job `php`, **après** les tests et **uniquement** sur `push` vers `main` :

```yaml
      - name: 🎨 Commit du CSS buildé
        if: github.event_name == 'push' && github.ref == 'refs/heads/main'
        run: |
          php bin/console tailwind:build --minify
          if git diff --quiet -- var/tailwind/app.built.css; then
            echo "CSS inchangé"
            exit 0
          fi
          git config user.name  "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add var/tailwind/app.built.css
          git commit -m "🎨 chore(assets): rebuild Tailwind [skip ci]"
          git push
```

- **`[skip ci]` est obligatoire** : sans lui, ce commit relance la CI, qui recommitte, à l'infini.
- Le job doit avoir `permissions: contents: write` pour pouvoir pousser.
- Le `checkout` doit être fait avec `persist-credentials: true` (défaut) pour que le `git push` soit authentifié.

> **Conséquence sur le SHA déployé** : le commit du CSS arrive *après* le merge, donc `origin/main` avance une seconde fois. Ce n'est pas un problème — le cron nocturne lit `main` bien plus tard et récupère naturellement le dernier état, CSS inclus.

> **Piège local découvert à l'implémentation** : `tests/Integration/TailwindBuildTest.php` attend **>1000 lignes** dans `var/tailwind/app.built.css` pour vérifier qu'il ne s'agit pas d'un placeholder. Un CSS **minifié** (`--minify`) tient sur une poignée de lignes même à 80+ Ko et fait échouer ce test. Comme le CSS commité par la CI sur `main` est minifié, un `git pull` en local peut ramener ce fichier minifié et casser la suite locale. **Rebuilder sans minification avant de lancer les tests en local** (`composer build-assets`, qui appelle `tailwind:build` sans `--minify`) — cohérent avec ce que ce script fait déjà. Ne pas "corriger" `TailwindBuildTest` pour accepter un CSS minifié : son rôle est de détecter un placeholder oublié, pas de valider un format de build.

### 6.3 `.gitignore`

- **Retirer** `var/tailwind/app.built.css` (désormais suivi)
- **Ajouter** `.deployed-sha`

> Vérifier qu'aucune règle large type `var/*` n'annule le retrait ; si c'est le cas, ajouter une exception explicite `!var/tailwind/app.built.css`.

### 6.4 Service de notification

Conforme à `.claude/architecture.md` : la commande délègue, le service travaille.

- `src/Interface/DeployNotificationMailerInterface.php`
- `src/Service/DeployNotificationMailer.php` — calqué sur `BroadcastMailer` (même `from`, même `EmailBranding::ACCENT_COLOR`)

```php
public function sendDeployReport(array $results): void;
// $results : [['instance'=>'yannick','status'=>'ok'|'failed'|'skipped','step'=>?string,'sha'=>?string], …]
```

- Destinataire : `DEPLOY_REPORT_EMAIL` (déjà posé dans le `.env.local` de `ronan` le 2026-09-12). Vide → log warning, **pas d'exception**.
- **`from` = `no-reply@lenouvel.me` impérativement** — cf. piège SPF/`From` de #378 : un autre domaine est droppé silencieusement par Gmail.
- Si **toutes** les instances sont en `skipped` (aucun merge depuis la veille), **n'envoyer aucun email** — sinon un mail quotidien inutile finit par être ignoré, et un vrai échec passerait inaperçu.

### 6.5 `src/Command/DeployQueueNotifyCommand.php`

```bash
php bin/console app:deploy-queue:notify report --file=/home9/ron2cuba/.deploy-report-2026-09-13.log
```

- Parse le fichier (format `<prenom>|<statut>|<étape>|<sha>`), appelle le service, puis **supprime le fichier**.
- Fichier absent → sortie 0 avec warning (aucun cron n'a tourné, ce n'est pas une erreur).
- Ligne malformée → l'ignorer en la loguant, ne jamais faire échouer l'envoi pour autant.

### 6.6 `templates/emails/deploy_report.html.twig`

Calqué sur `broadcast_message.html.twig`. Contenu :

- Titre « Déploiement nocturne »
- Tableau instance / statut (✅ déployé, ⏭️ déjà à jour, ❌ échec) / étape en échec / SHA court
- Mention que les instances en échec seront retentées la nuit suivante

### 6.7 `bin/deploy-all.sh` — filet de secours

Conservé pour déployer à la demande (urgence, ou serveur qui n'a pas pris la nuit). Modifications minimales :

- ajouter le flag `--now` (déploiement immédiat des 7, comportement actuel)
- **sortir `php bin/console tailwind:build` de la boucle** : il produit le même fichier pour les 7 instances, le rejouer 7 fois est du gaspillage (vrai aussi dans le code actuel)
- après un déploiement manuel réussi sur une instance, **écrire `.deployed-sha`** — sinon le cron de la nuit suivante redéploierait inutilement
- ne pas chercher à y ajouter de logique de pile : il n'y en a plus

---

## 7. Crons cPanel — à créer manuellement après le merge

> **Contrainte LVE critique** (incident #395/#396) : les 7 instances partagent **un seul** compte cPanel, donc **une seule** limite LVE. Un déploiement est bien plus lourd qu'une purge. Lancer plusieurs déploiements à la même minute tuera des process sans trace applicative. **Étalement de 5 min minimum.**
>
> Les crons existants occupent `3h00` → `4h00` (`purge-revoked`, `process-missing`). La fenêtre de déploiement est placée **avant**, de `1h00` à `2h45`.
>
> **Fuseau vérifié le 2026-09-12** : le serveur est en **CEST**, soit l'heure de Paris. Les horaires ci-dessous sont donc directement corrects, sans conversion.

| Instance | Horaire | Rôle |
|----------|---------|------|
| ronan | `0 1 * * *` | canari — échoue en premier si le déploiement est cassé |
| yannick | `5 2 * * *` | |
| coralie | `10 2 * * *` | |
| elea | `15 2 * * *` | |
| corentin | `20 2 * * *` | |
| damien | `25 2 * * *` | |
| baptiste | `30 2 * * *` | |
| *(rapport)* | `45 2 * * *` | envoi de l'email, depuis `ronan` |

```bash
0 1 * * * flock -n /home9/ron2cuba/.deploy-nightly-ronan.lock /bin/bash /home9/ron2cuba/ronan.lenouvel.me/bin/deploy-nightly.sh ronan >> /home9/ron2cuba/ronan.lenouvel.me/var/log/deploy-nightly.log 2>&1
```

```bash
45 2 * * * flock -n /home9/ron2cuba/.deploy-report.lock /usr/local/bin/php /home9/ron2cuba/ronan.lenouvel.me/bin/console app:deploy-queue:notify report --file=/home9/ron2cuba/.deploy-report-$(date +\%Y-\%m-\%d).log --env=prod >> /home9/ron2cuba/ronan.lenouvel.me/var/log/deploy-nightly.log 2>&1
```

> **Piège `%` en crontab** : un `%` non échappé est interprété comme un saut de ligne et tronque la commande. D'où `\%Y-\%m-\%d`.

> Comme le cron Messenger, **ces crons sont à ajouter pour chaque nouvelle instance**.

---

## 8. Ordre d'exécution TDD

Méthodologie : **RED → GREEN → REFACTOR**, jamais de code avant le test (`.claude/tdd.md`).

> L'étape 0 (validation de connectivité) est **déjà faite** — verdict au §2.1, architecture ajustée en conséquence. Ne pas la rejouer.

### Étape 1 — Service de notification

1. 🔴 `tests/Service/DeployNotificationMailerTest.php`
   - un email au bon destinataire, avec le bon sujet
   - distingue `ok` / `failed` / `skipped`, mentionne l'étape en échec
   - **tout en `skipped` → aucun email envoyé**
   - `DEPLOY_REPORT_EMAIL` vide → aucun email, aucune exception
   - `from` = `no-reply@lenouvel.me`
2. 🟢 Interface + service + template Twig
3. ✅ `✨ feat(deploy): service de notification du déploiement nocturne (#421)`

### Étape 2 — Commande console

1. 🔴 `tests/Command/DeployQueueNotifyCommandTest.php`
   - parse un fichier de rapport valide → service appelé avec les bonnes données
   - fichier supprimé après envoi
   - fichier absent → sortie 0 + warning
   - ligne malformée → ignorée, les autres traitées
2. 🟢 `src/Command/DeployQueueNotifyCommand.php`
3. ✅ `✨ feat(deploy): commande d'envoi du rapport de déploiement (#421)`

### Étape 3 — Script nocturne (le cœur)

> Si `tests/bash/` n'existe pas, créer un runner minimal (script bash exécutant les fonctions `test_*` et comptant les échecs). Ne pas introduire `bats` sans validation préalable.

1. 🔴 `tests/bash/deploy-nightly-test.sh` — `git`, `composer`, `php` remplacés par des stubs dans le `PATH` :
   - SHA distant == `.deployed-sha` → **aucune** commande de déploiement, ligne `skipped`, sortie 0
   - `.deployed-sha` absent → déploiement lancé
   - SHA différent → étapes appelées **dans l'ordre attendu**
   - succès → `.deployed-sha` contient le nouveau SHA, ligne `ok`
   - échec à une étape → `.deployed-sha` **inchangé**, ligne `failed|<étape>`, sortie 1
   - **les étapes suivantes ne sont pas exécutées** après un échec
   - `git checkout` cible bien le SHA distant
2. 🟢 `bin/deploy-nightly.sh`
3. ✅ `✨ feat(deploy): script de déploiement nocturne autonome (#421)`

### Étape 4 — CI, gitignore, deploy-all

1. 🟢 Modification de `ci.yml` (commit du CSS, `[skip ci]`, permissions)
2. 🟢 `.gitignore` : retirer le CSS, ajouter `.deployed-sha`
3. 🟢 `bin/deploy-all.sh` : `--now`, build hors boucle, écriture de `.deployed-sha`
4. ✅ `✨ feat(deploy): CSS Tailwind versionné et déploiement manuel aligné (#421)`

> Ces changements ne sont pas testables unitairement de façon utile (CI et script d'infra). Les valider par la vérification manuelle du §9.

### Étape 5 — Documentation

- `.claude/deploiement.md` : section « Déploiement nocturne autonome » — principe, crons, diagnostic (`cat .deployed-sha`, `tail var/log/deploy-nightly.log`), procédure pour une 8ᵉ instance
- `.claude/cicd.md` : **corriger** l'affirmation « déploiement manuel, automatisation impossible », et documenter que le WAF o2switch bloque `/deploy.php` pour les IPs non-françaises (§2.1)
- `.github/avancement.md` : suivi
- ✅ `📝 docs(deploy): documentation du déploiement nocturne autonome (#421)`

### Étape 6 — Nettoyage

- Supprimer `.github/workflows/probe-o2switch.yml` (workflow de test jetable, déjà sur la branche)
- ✅ `🔧 chore(ci): retirer le workflow de test de connectivité (#421)`

## 9. Configuration manuelle (par Ronan, après merge)

### 9.1 Déploiement d'amorçage — obligatoire et en premier

```bash
bash bin/deploy-all.sh --now
```

Sans cela, aucune instance ne possède `bin/deploy-nightly.sh` et les crons échoueraient tous. C'est l'unique déploiement manuel du chantier ; ensuite, tout est autonome.

Puis initialiser l'état sur chaque instance, pour éviter un redéploiement inutile la nuit suivante :

```bash
source .secrets
for P in ronan yannick coralie elea corentin damien baptiste; do
  ssh -i "$SSH_KEY_PATH" ron2cuba@lenouvel.me \
    "cd ~/$P.lenouvel.me && git rev-parse HEAD > .deployed-sha && echo \"$P ok\""
done
```

### 9.2 Créer les 8 crons du §7

Via cPanel → Tâches cron. Respecter les horaires étalés (contrainte LVE).

### 9.3 Vérifications déjà faites — ne pas refaire

| Point | État |
|-------|------|
| Fuseau du serveur | ✅ **CEST** (heure de Paris) — vérifié le 2026-09-12, les horaires du §7 sont directement corrects |
| `DEPLOY_REPORT_EMAIL` sur `ronan` | ✅ posé dans `.env.local` le 2026-09-12 |

### 9.4 Nettoyage du webhook — à trancher par Ronan

Les tests du §2.1 ont laissé en place une configuration désormais **inutile** :

- `DEPLOY_WEBHOOK_SECRET` dans les secrets GitHub
- `DEPLOY_WEBHOOK_SECRET` dans le `.env.local` des 7 instances
- variable GitHub `DEPLOY_INSTANCES`
- `public/deploy.php` lui-même

`public/deploy.php` est **exposé publiquement et exécute `git pull` + `composer install`** dès qu'il reçoit un POST signé sur `refs/heads/main`. Il n'est plus utilisé par rien après ce chantier, et le WAF ne le protège que pour les IPs étrangères.

**Recommandation : le supprimer**, ainsi que les secrets associés. Un fichier qui déploie en production et que plus personne n'utilise est une surface d'attaque sans contrepartie. À faire dans un commit dédié, après validation du nouveau flux en conditions réelles (garder le filet tant que le nocturne n'a pas tourné au moins une fois).

---

## 10. Critères d'acceptation

- [ ] Un merge sur `main` **ne déclenche aucun changement** sur les instances en journée
- [ ] La CI committe `var/tailwind/app.built.css` sur `main` après un merge, avec `[skip ci]`
- [ ] Ce commit de CSS **ne relance pas** la CI (pas de boucle)
- [ ] La CI **ne committe pas** de CSS sur une branche de PR
- [ ] La nuit, chaque instance se met à jour sur le dernier `main` sans intervention
- [ ] `ronan` (1h00) se déploie avant les autres (2h05→2h30) — rôle de canari
- [ ] Une instance déjà à jour ne fait **rien** (sortie 0, statut `skipped`, pas de `composer install` inutile)
- [ ] Un échec laisse `.deployed-sha` inchangé → nouvelle tentative la nuit suivante
- [ ] Un échec n'exécute **pas** les étapes suivantes (jamais de déploiement à moitié)
- [ ] Un seul email récapitulatif arrive à `2h45`, listant les 7 instances
- [ ] **Aucun email** si toutes les instances sont `skipped`
- [ ] Le CSS servi correspond bien au commit déployé
- [ ] `bash bin/deploy-all.sh --now` fonctionne toujours et met à jour `.deployed-sha`
- [ ] `.deployed-sha` est dans `.gitignore` et n'est jamais committé
- [ ] Suite PHPUnit verte avant push, tests bash verts

---

## 11. Pièges connus — ne pas y retomber

| Piège | Source | Conséquence si ignoré |
|-------|--------|------------------------|
| Un sous-shell par étape de déploiement | incident 2026-07-23 | OOM LVE, process `Killed` sans aucune trace |
| Étaler les crons de ≥5 min entre instances | #395/#396 | Process tués silencieusement par LVE |
| `mkdir -p var/log` avant toute redirection `>>` | `.claude/deploiement.md` | La commande ne s'exécute **jamais**, sans erreur visible |
| `[skip ci]` sur le commit du CSS | §6.2 | Boucle infinie CI → commit → CI |
| Commit du CSS **seulement** sur `push: main` | §6.2 | La CI committerait sur toutes les branches de PR |
| `git checkout --force` | §6.1 | Checkout en échec à cause de `deploy-info.html.twig` modifié sur le serveur |
| `From` = `no-reply@lenouvel.me` uniquement | #378 + SPF | Email droppé silencieusement par Gmail |
| Échapper `%` en `\%` dans crontab | §7 | Commande cron tronquée |
| Arrêt immédiat à la première étape en échec | §6.1 | Instance déployée à moitié, pire que pas déployée |
| Ne pas rebuilder Tailwind sur le serveur | `deploy-all.sh` L214 | Build en échec ou CSS incohérent |
| `.secrets` écrase tout override en ligne de commande | `.claude/deploiement.md` | « Ça ne prend pas ma variable » |
| Rebuild assets après modif `.twig` (`composer build-assets`) | mémoire projet | `TailwindBuildTest` échoue faussement |
| Jamais de commit direct sur `main` | `CLAUDE.md` | — |

---

## 12. Branche et PR

```bash
git checkout main && git pull
git checkout -b feature/421-deploiement-differe-nocturne
```

- Commits atomiques, un par étape TDD (jamais `git add .`)
- Pas de ligne `Co-Authored-By`
- PR avec **`Closes #421`** dans la description ; mentionner que #288 devient caduc
- **Label obligatoire** immédiatement après `gh pr create` (`feature` + `ci`)
- Relancer la suite de tests **juste avant** le push
- CI verte avant merge, puis `--merge` (jamais `--squash`)

> **Attention au premier merge** : cette PR ajoute `deploy.yml`, qui se déclenchera dès le merge — sur du code où les secrets GitHub ne seront pas encore configurés (§9). Le workflow doit donc **échouer proprement** si `DEPLOY_WEBHOOK_SECRET` est absent, sans laisser les instances dans un état incohérent. Le prévoir dans `notify-instances.sh` : secret vide → message clair et sortie 0.
