# Déploiement HomeCloud — Guide opérationnel

Cible : hébergement mutualisé **o2switch**, un sous-domaine par instance (`<prenom>.lenouvel.me`).

---

## Méthode de déploiement

SSH depuis la machine locale via les scripts `bin/`.

> Le déploiement automatique GitHub Actions (webhook) ne fonctionne **pas** sur o2switch — les IPs Microsoft Azure (GitHub Actions) sont bloquées par le firewall. Les scripts SSH sont la seule méthode fiable.

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
SSH_KEY_PATH=/home/ronan/.ssh/o2switch
```

**`.secrets.<prenom>`** — par instance, uniquement pour `--init` :

```bash
DB_PASSWORD_PRESET=<mot de passe MySQL de l'instance>
```

### Mise à jour de toutes les instances

Après chaque merge sur `main` :

```bash
bash bin/deploy-all.sh
```

Chaîne exécutée sur chaque serveur :
`git pull` → `composer install --no-dev` → `cache:clear` → `migrations` → `asset-map:compile`

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
- La clé `o2switch-homecloud` doit être présente et `authorized`
- Clé privée locale : `~/.ssh/o2switch`

---

## Étapes manuelles post-déploiement initial

Après `--init` ou `bin/deploy.sh`, **en SSH** :

```bash
ssh -i ~/.ssh/o2switch ron2cuba@lenouvel.me
cd /home9/ron2cuba/<prenom>.lenouvel.me

# Créer le premier utilisateur admin
php bin/console app:create-user '<email>' '<password>' <prenom> --env=prod
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
