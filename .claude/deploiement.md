# Déploiement HomeCloud — Guide opérationnel

Cible : hébergement mutualisé **o2switch**, un sous-domaine par instance (`<prenom>.lenouvel.me`).

---

## Méthode de déploiement

SSH depuis la machine locale via les scripts `bin/`.

> Le déploiement automatique GitHub Actions (webhook) ne fonctionne **pas** sur o2switch — les IPs des runners GitHub Actions sont bloquées par le firewall SSH. Les scripts SSH sont la seule méthode fiable aujourd'hui (#288, en pause). Le webhook `public/deploy.php` existe dans le repo mais est cassé et inutilisé.

### Piège IP dynamique / VPN d'entreprise

Sans IP fixe (FAI grand public ou VPN d'entreprise désactivé), l'IP change entre deux sessions et le SSH se bloque silencieusement (timeout, pas de refus explicite) si elle n'est plus whitelistée dans cPanel → Sécurité → Accès SSH → Autorisation SSH. Un VPN d'entreprise peut à l'inverse bloquer l'accès à cPanel lui-même — pas de solution simple aux deux contraintes à la fois.

o2switch expose une micro-API `SshWhitelist` (port cPanel 2083, indépendante du port SSH 22) pour whitelister une IP dynamiquement sans accès SSH préalable — utile en dépannage :

```bash
curl -s -u "<user_cpanel>:<mdp_cpanel>" \
  "https://<serveur>.o2switch.net:2083/execute/SshWhitelist/add?address=$(curl -4 -s ifconfig.me)&port=22"
```

Authentification recommandée par o2switch : token API cPanel ("Manage API Tokens") — indisponible sur ce compte au 2026-07-20 ("bloqué à des fins de sécurité" selon le support), d'où le repli sur les identifiants cPanel classiques ci-dessus. Max 5 exceptions simultanées sur le compte.

> **Piège identifiants** (vécu le 2026-07-23) — `.secrets` contient plusieurs
> mots de passe pour des comptes différents (le compte cPanel `ron2cuba` ET le
> compte mail `ronan@lenouvel.me`, entre autres). Utiliser le mauvais mot de
> passe renvoie `invalid_login` côté API `SshWhitelist`, mais la réponse est
> une page HTML de login cPanel (pas un JSON d'erreur explicite) — facile à
> confondre avec un problème réseau/API si on ne grep pas le corps de la
> réponse. Vérifier `msg_code:[invalid_login]` dans le HTML avant de suspecter
> autre chose. **Aussi** : cette API n'est **pas** la méthode retenue pour ce
> projet — le SSH direct (whitelisting manuel via cPanel si besoin) reste la
> voie normale ; ne pas y revenir par réflexe sans demander d'abord.

---

## Déploiement multi-instances — `bin/deploy-all.sh`

Script principal de production. Déploie sur toutes les instances listées dans `.deploy-targets`.

### Prérequis

**`.deploy-targets`** — à la racine, non versionné, un prénom par ligne :

```text
ronan
alice
# bob    ← commenté = ignoré
```

**`.secrets`** — variables globales SSH :

```bash
SSH_KEY_PATH=/home/ronan/.ssh/o2switch-new
SSH_KEY_PASSPHRASE=
```

> Voir « Mode opératoire — clé SSH o2switch » ci-dessous avant de générer ou remplacer cette clé.

**`.secrets.<prenom>`** — par instance, uniquement pour `--init` :

```bash
DB_PASSWORD_PRESET=<mot de passe MySQL de l'instance>
```

### Mise à jour de toutes les instances

Après chaque merge sur `main` :

```bash
bash bin/deploy-all.sh
```

Chaîne exécutée sur chaque serveur, **une connexion SSH par étape** (depuis le
2026-07-23, cf. ci-dessous) :
`git pull` → `composer install --no-dev --no-scripts` → `install-ffmpeg` → `cache:clear` → `assets:install` → `importmap:install` → `migrations` → `asset-map:compile` → `deploy-info`

`--no-scripts` évite l'exécution des scripts post-install Symfony Flex (`auto-scripts` : `cache:clear`, `assets:install %PUBLIC_DIR%`, `importmap:install`) pendant `composer install` — ajouté suite à des `Killed` (OOM) répétés sur le mutualisé o2switch pendant cette phase (2026-07-22), alors que le `composer install` en lui-même n'était pas en cause (`Nothing to install, update or remove`, dépendances déjà à jour). Les 3 commandes sautées sont rappelées explicitement ensuite, une par une — ne pas en retirer une sans vérifier qu'elle est bien redondante ailleurs dans le script.

> **Cause racine identifiée et corrigée (2026-07-23)** — les `Killed` (OOM)
> observés à plusieurs reprises sur des instances isolées (une seule sur les
> 7, jamais la même, commande tuée différente à chaque fois : migrations,
> `cache:clear`…) ne viennent **pas** d'un manque de RAM sur la machine (`free
> -h` montre 15 Gio libres sur 125 Gio total) mais d'un **quota mémoire par
> compte cPanel** — le compte tourne dans un LVE CloudLinux
> (`/proc/self/cgroup` → `lve508`), un cgroup isolé de la RAM système totale.
> `deploy-all.sh` enchaînait toutes les commandes `bin/console` dans **un seul
> process SSH** ; l'empreinte mémoire (opcache, buffers) s'accumulait d'une
> commande à l'autre dans ce process jusqu'à dépasser le quota du LVE sur une
> étape qui, seule, tient largement dans ce même quota.
>
> **Correctif** : chaque étape (`composer install`, `cache:clear`,
> `assets:install`, `importmap:install`, `migrations`, `asset-map:compile`…)
> ouvre maintenant sa **propre connexion SSH** (fonction `run_step` dans
> `bin/deploy-all.sh`) — chaque étape démarre donc dans un process shell
> neuf côté serveur, sans hériter de l'empreinte mémoire des étapes
> précédentes. Validé le 2026-07-23 : 7/7 instances déployées sans échec,
> y compris les deux qui avaient été tuées par OOM lors des deux
> déploiements précédents avec l'ancienne version (une seule connexion SSH).
>
> Si un `✖ <instance> — échec à l'étape « <nom> »` apparaît malgré tout,
> rejouer uniquement cette étape en SSH direct (cf. exemple dans la section
> `bin/deploy.sh`) plutôt que de relancer tout `deploy-all.sh`/`deploy.sh`
> (risque de retomber sur le piège `PRENOM_PRESET` ci-dessus).

### Premier déploiement de toutes les instances

```bash
bash bin/deploy-all.sh --init
```

Pour chaque prénom dans `.deploy-targets` :

1. Charge `.secrets.<prenom>` (DB_PASSWORD_PRESET requis)
2. Génère `APP_SECRET` et `JWT_PASSPHRASE` localement
3. Clone le repo sur le serveur
4. Crée `.env.local` avec toutes les variables
5. Lance `composer install`, `cache:clear`, `migrations`, `lexik:jwt:generate-keypair`, `asset-map:compile`

> Après `--init`, créer le premier utilisateur manuellement en SSH (voir section ci-dessous).

---

## Déploiement ciblé — `bin/deploy.sh`

Pour une instance spécifique (beta, test, ou nouvelle instance en solo) :

```bash
bash bin/deploy.sh           # premier déploiement interactif
bash bin/deploy.sh --update  # mise à jour d'une seule instance
```

> **Piège `PRENOM_PRESET`** (vécu le 2026-07-23) — `.secrets` contient un
> `PRENOM_PRESET="<prenom>"` qui court-circuite **toute** saisie interactive,
> y compris passer le prénom via un heredoc/stdin (`bash bin/deploy.sh <<<
> "corentin"`) : le script lit la variable d'environnement avant de tenter le
> `read`, donc le stdin fourni est silencieusement ignoré. Résultat vécu :
> vouloir cibler `corentin` a relancé un déploiement complet sur `ronan` (le
> prénom figé dans `.secrets`) sans message d'erreur.
>
> Pour cibler une autre instance ponctuellement : éditer `PRENOM_PRESET` dans
> `.secrets` directement (puis le remettre après), ou commenter la ligne pour
> forcer la saisie interactive. Ne jamais supposer qu'un argument ou un stdin
> passé en ligne de commande écrasera le preset.
>
> Pour rejouer une seule étape en échec sur une instance précise (ex.
> migration tuée par OOM, cf. ci-dessous) sans relancer tout `deploy.sh` :
> SSH direct, ciblé sur le dossier de l'instance concernée :
>
> ```bash
> source .secrets
> ssh -i "$SSH_KEY_PATH" ron2cuba@lenouvel.me \
>     "cd <prenom>.lenouvel.me && php bin/console doctrine:migrations:migrate --no-interaction --env=prod"
> ```

---

## Prérequis cPanel — nouvelle instance

À faire une seule fois par sous-domaine dans cPanel avant tout déploiement.

### a) Créer le sous-domaine

- cPanel → Domaines → Sous-domaines
- Sous-domaine : `<prenom>`, domaine : `lenouvel.me`
- Chemin racine : `/<prenom>.lenouvel.me`

### b) Créer la base de données MySQL

- cPanel → Bases de données MySQL
- Base : `ron2cuba_<prenom>` (préfixe imposé par o2switch)
- Utilisateur : `ron2cuba_<prenom>`
- Mot de passe : à noter dans `.secrets.<prenom>`
- Affecter l'utilisateur à la base avec tous les droits

### c) Autoriser l'IP SSH

- cPanel → Sécurité → Accès SSH → Autorisation SSH
- Ajouter ton IP (entrante + sortante, port 22)
- Vérifier ton IP : `curl ifconfig.me`

### d) Clé SSH autorisée

- cPanel → Sécurité → Accès SSH → Gérer les clés
- La clé `o2switch-homecloud-new` doit être présente et **`authorized`** (pas seulement « importée » — voir mode opératoire ci-dessous)
- Clé privée locale : `~/.ssh/o2switch-new`

---

## Mode opératoire — clé SSH o2switch

### Piège vécu (2026-07-22)

L'ancienne clé (`~/.ssh/o2switch`, passphrase documentée dans `.secrets`) a cessé
de fonctionner sans explication : `ssh-keygen -y -f ~/.ssh/o2switch` avec la
passphrase documentée renvoyait *"incorrect passphrase supplied to decrypt
private key"*. Vérifié : le fichier de clé était intact (`file` confirme un
format OpenSSH valide), la passphrase documentée ne contenait aucun caractère
caché (`od -c`). **Cause jamais identifiée** — la clé et sa passphrase
documentée avaient simplement divergé.

Contournement adopté : régénérer une clé neuve plutôt que continuer à chercher
la cause.

### Régénérer la clé si l'authentification par clé casse

**1. Générer une nouvelle paire, sans passphrase** (le déploiement tourne en
script non interactif — une passphrase impose un `ssh-agent` à chaque
exécution, source de bugs comme celui-ci) :

```bash
ssh-keygen -t ed25519 -f ~/.ssh/o2switch-new -N "" -C "o2switch-homecloud-new"
```

**2. Récupérer la clé publique à importer :**

```bash
cat ~/.ssh/o2switch-new.pub
```

**3. Importer dans cPanel** → Sécurité → Gestionnaire de clés SSH → « Importer
une clé » :

- Nom : un nom **différent** de l'ancienne clé (ex. `o2switch-homecloud-new`)
- Coller uniquement la **clé publique** dans le champ prévu
- **Ne jamais coller la clé privée dans cPanel** — aucun besoin, c'est un
  import de clé publique, un champ privé rempli est un signal d'alerte
- Laisser le champ passphrase vide (clé générée sans passphrase)

**4. Vérifier le statut "authorized"** (pas juste "importée") dans le tableau
du Gestionnaire de clés SSH — c'est ce statut, pas l'import seul, qui active
réellement l'authentification par clé publique côté serveur.

**5. Tester en non-interactif AVANT de toucher `.secrets`** — c'est le test
qui aurait évité l'essentiel du temps perdu la dernière fois (une connexion
manuelle interactive avec mot de passe réussit même quand l'auth par clé est
cassée, ce qui donne un faux sentiment que "ça marche") :

```bash
ssh -i ~/.ssh/o2switch-new -p 22 -o ConnectTimeout=10 -o BatchMode=yes \
    ron2cuba@lenouvel.me "echo OK"
```

`BatchMode=yes` interdit tout fallback interactif (mot de passe, agent
askpass) — si la clé n'est pas acceptée, la commande échoue immédiatement au
lieu de sembler bloquée.

**6. Seulement après un `OK` confirmé**, mettre à jour `.secrets` :

```bash
SSH_KEY_PATH="$HOME/.ssh/o2switch-new"
SSH_KEY_PASSPHRASE=""
```

> `.secrets` est sourcé par `bin/deploy-all.sh` — un `SSH_KEY_PATH=...` passé
> en préfixe de commande shell (`SSH_KEY_PATH=~/.ssh/o2switch-new bash
> bin/deploy-all.sh`) est **écrasé** par le `source .secrets` du script (ligne
> ~17). Modifier `.secrets` directement est le seul moyen fiable de changer la
> clé utilisée par le déploiement — un override en ligne de commande ne suffit
> pas et fait perdre du temps à chercher pourquoi "ça ne prend pas en compte".

---

## Étapes manuelles post-déploiement initial

Après `--init` ou `bin/deploy.sh`, **en SSH** :

```bash
ssh -i ~/.ssh/o2switch ron2cuba@lenouvel.me
cd /home9/ron2cuba/<prenom>.lenouvel.me

# Créer le premier utilisateur admin sans mot de passe :
# la commande affiche un lien de définition de mot de passe (valable 1h)
# à ouvrir dans le navigateur — évite de taper un mot de passe en clair
# sur la ligne de commande / dans l'historique bash SSH.
php bin/console app:create-user '<email>' <prenom> --env=prod
```

> `lexik:jwt:generate-keypair` et `asset-map:compile` sont déjà gérés par les scripts.

---

## Chemins importants sur o2switch

| Ressource   | Chemin                                                    |
|-------------|-----------------------------------------------------------|
| Composer    | `/usr/local/bin/composer`                                 |
| PHP         | `/usr/local/bin/php`                                      |
| Projet      | `/home9/ron2cuba/<prenom>.lenouvel.me/`                   |
| Logs deploy | `/home9/ron2cuba/<prenom>.lenouvel.me/var/log/deploy.log` |
| SSH user    | `ron2cuba@lenouvel.me`                                    |

---

## Worker Messenger — obligatoire

Le traitement des médias (EXIF, vignettes) part en file asynchrone
(`config/packages/messenger.yaml` route `App\Message\MediaProcessMessage`
vers le transport `async`, stocké en base). **Sans worker qui dépile cette
file, aucune vignette n'est générée** : les fichiers s'uploadent et
apparaissent dans *Mes fichiers*, mais jamais dans la Galerie. Aucune erreur
n'est levée — la photo « disparaît » silencieusement.

Supervisor n'existe pas sur un hébergement mutualisé (pas de systemd) : on
passe par une tâche cron cPanel qui relance le worker à intervalle régulier.

| Champ      | Valeur        |
|------------|---------------|
| Intervalle | `*/5 * * * *` |

```bash
flock -n /home9/ron2cuba/.messenger-<prenom>.lock /usr/local/bin/php /home9/ron2cuba/<prenom>.lenouvel.me/bin/console messenger:consume async --time-limit=290 --memory-limit=128M --env=prod >> /home9/ron2cuba/<prenom>.lenouvel.me/var/log/messenger.log 2>&1
```

- `flock -n` : si un worker tourne déjà, la tentative suivante ne fait rien —
  sans lui, un nouveau processus s'accumulerait toutes les 5 minutes.
- `--time-limit=290` : le worker meurt avant le prochain cron (300 s) ; sans
  lui, `flock` bloquerait tout redémarrage.
- `--memory-limit=128M` : PHP fuit en processus long, le worker redémarre
  proprement avant de saturer.

Diagnostic :

```bash
php bin/console messenger:stats --env=prod   # messages en attente
tail -20 var/log/messenger.log               # erreurs du worker
```

**Critère de bon fonctionnement** : uploader une photo → elle apparaît dans
la Galerie avec sa vignette en moins de 5 minutes.

---

## Variables `.env.local` sur le serveur

À créer manuellement sur chaque instance (jamais dans git) :

```bash
APP_ENV=prod
APP_SECRET=<généré par le script>
DATABASE_URL=mysql://ron2cuba_<prenom>:<password>@127.0.0.1:3306/ron2cuba_<prenom>?serverVersion=mariadb-10.6.0&charset=utf8mb4
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
MAILER_DSN=smtp://<user>:<password>@lenouvel.me:465
```

> Généré automatiquement par `bin/deploy-all.sh --init`. Pour `bin/deploy.sh`, le script le crée aussi.
>
> `MAILER_DSN` vient de `MAILER_DSN_PRESET` dans `.secrets` (global, partagé par toutes
> les instances — même boîte SMTP `lenouvel.me` pour tout le monde). Sans cette clé,
> l'instance est créée avec `MAILER_DSN=null://null` : aucun email d'invitation ni de
> réinitialisation de mot de passe ne part (cf. `GuestAccountCreator`).

### `MAILER_DSN` — obligatoire pour les emails (dont la notif de fin de lot)

Par défaut `MAILER_DSN=null://null` (`.env`) : **aucun email n'est envoyé**. Les
flux transactionnels (reset password, invitation, notification de partage) et la
**notification de fin de traitement d'un lot lourd** (#260 — email « vos fichiers
sont prêts » quand le worker a terminé un lot deferred) nécessitent un DSN SMTP
réel, à renseigner sur chaque instance (jamais dans git — SMTP o2switch injoignable
depuis localhost, port 465 bloqué). Sans lui, le traitement se fait quand même,
seul l'email de notification manque.

---

## Diagnostic — erreurs fréquentes

| Symptôme                             | Cause                            | Solution                                                     |
|--------------------------------------|----------------------------------|--------------------------------------------------------------|
| 500 sur le site                      | Assets non compilés              | `php bin/console asset-map:compile`                          |
| 500 sur le site                      | `SecRuleEngine` dans `.htaccess` | Supprimer ce bloc du `.htaccess` serveur                     |
| `git pull` bloqué                    | Fichier untracked sur le serveur | `rm -f <fichier>` puis `git pull`                            |
| DB access denied                     | `.env.local` incorrect           | Vérifier `DATABASE_URL` et mot de passe cPanel               |
| SSH refusé                           | IP non whitelistée               | cPanel → Accès SSH → Autorisation SSH                        |
| Pas de vignette/EXIF dans la Galerie | Worker Messenger absent          | Vérifier la tâche cron (voir « Worker Messenger » ci-dessus) |
| Messages Messenger jamais consommés (`messenger:stats` ne baisse jamais) | `var/log/` absent sur le serveur | Le cron redirige vers `var/log/messenger.log` (`>>`) : si le dossier n'existe pas, la redirection échoue et **la commande PHP ne s'exécute jamais**, sans erreur visible. `mkdir -p var/log` (corrigé dans `bin/deploy-all.sh` depuis le 2026-07-18, mais les instances déployées avant cette date doivent l'avoir manuellement). |
| Une seule instance en `❌` sur un `deploy-all.sh`, différente à chaque run, sans message d'erreur clair | Instabilité SSH transitoire (timeout/latence ponctuelle sur le mutualisé, distincte du piège OOM/LVE déjà documenté ci-dessus) | Relancer simplement `bash bin/deploy-all.sh` une seconde fois — le script est idempotent (`git pull`/`composer install`/migrations ne font rien si déjà à jour) ; vécu le 2026-07-23 sur `yannick.lenouvel.me`, résolu au 2ᵉ run sans autre action. Si l'échec persiste sur la même instance après 2 essais, chercher la cause précise (cf. ligne OOM/LVE ci-dessus) plutôt que de continuer à relancer en boucle. |
