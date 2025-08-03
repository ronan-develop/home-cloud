# Contexte et Objectifs du projet Home Cloud

## Objectif principal

Créer une solution d’hébergement personnel (type cloud privé) pour stocker, partager et synchroniser photos et documents, sans dépendre des services Apple, Google, etc.

## Architecture cible

- Application Symfony (PHP, version LTS 7.3.2 à ce jour) hébergée chez O2Switch (hébergement mutualisé, pas de Docker possible)
- Application conçue en mode multi-tenant : chaque sous-domaine (ex : ronan.lenouvel.me, elea.lenouvel.me) correspond à un espace privé et isolé pour un utilisateur ou un groupe, tout en partageant le même cœur applicatif et la même base de code.
- Chaque utilisateur disposera de sa propre base de données MySQL dédiée, afin de garantir une isolation stricte des données et d’éviter tout mélange entre les espaces privés.
- Serveur web Caddy installé sur l’espace d’hébergement (reverse proxy, HTTPS, gestion des routes)
- Domaine principal : lenouvel.me
- Sous-domaines pour chaque membre du foyer (ex : ronan.lenouvel.me, elea.lenouvel.me, yannick.lenouvel.me)
- Multi-tenant : chaque sous-domaine = un espace utilisateur isolé
- ⚠️ Le domaine racine lenouvel.me n’est jamais accessible directement : seules les requêtes vers des sous-domaines explicitement déclarés dans l’application sont autorisées.

## Contraintes techniques O2Switch

- Accès SSH utilisateur (pas root)
- Pas de Docker, pas d’installation de paquets système
- Serveur web et PHP gérés par l’hébergeur
- Possibilité d’utiliser la console Symfony, composer, npm, pip, etc.
- Déploiement via git/CI/CD possible
- Bases de données MySQL illimitées
- Espace disque illimité

## Sécurité & accès

- Accès SSH : ssh -p 22 <ron2cuba@abricot.o2switch.net> (mdp fourni)
- Base de données principale : ron2cuba_ronanroot (mdp fourni)
- Clé SSH gitlab fournie pour le déploiement

## Procédure d'accès SSH

Pour accéder à l’hébergement O2Switch en SSH :

1. Ouvre un terminal sur ta machine locale.
2. Lance la commande suivante :

   ```sh
   ssh -p 22 ron2cuba@abricot.o2switch.net
   ```

3. Saisis le mot de passe associé au compte lorsque demandé.
4. En cas d’erreur « Permission denied », vérifie l’orthographe du mot de passe et réessaie.
5. Une fois connecté, tu peux utiliser la console pour manipuler les fichiers, lancer des commandes Symfony, composer, etc.

## Fonctionnalités attendues

- Stockage et partage de fichiers/photos
- Interface web intuitive
- Gestion fine des utilisateurs et permissions
- Synchronisation multi-appareils
- Support multi-tenant (sous-domaines)

## Pourquoi PHP/Symfony ?

- Robuste pour la gestion HTTP, upload, sécurité
- Compatible avec les restrictions O2Switch
- Large écosystème et support

## Points d’attention

- Pas d’accès root, pas de customisation serveur profonde
- Pas de containers, pas de reverse proxy custom
- Tout doit fonctionner dans les limites d’un mutualisé PHP classique

## Résumé de la configuration serveur O2Switch

- **Version PHP CLI** : 8.3.23 (build: 15 juillet 2025)
- **Zend Engine** : v4.3.23
- **Mode** : NTS (Non Thread Safe)
- **Modules PHP actifs** :
  - bcmath, bz2, calendar, clos_ssa, Core, ctype, curl, date, dom, exif, fileinfo, filter, ftp, gd, gettext, hash, iconv, imap, intl, json, ldap, libxml, mbstring, mysqli, mysqlnd, openssl, pcntl, pcre, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, pgsql, Phar, posix, random, readline, Reflection, session, shmop, SimpleXML, soap, sockets, sodium, SPL, sqlite3, standard, sysvmsg, sysvsem, sysvshm, tidy, tokenizer, xml, xmlreader, xmlwriter, xsl, zip, zlib
- **Extensions PHP critiques (Symfony/Cloud)** :
  - curl, gd, intl, mbstring, mysqli, openssl, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, zip
- **Limites PHP** :
  - file_uploads : On
  - max_execution_time : 0 (illimité)
  - max_file_uploads : 20
  - memory_limit : 128M
  - post_max_size : 8M
  - upload_max_filesize : 2M
  - upload_tmp_dir : non défini
  - session.upload_progress.* : activé (voir détails ci-dessous)
- **Base de données** :
  - Serveur MariaDB 11.4.7 (client 15.2)
  - Commande : `mysql --version`
  - Remarque : le binaire `mysql` est déprécié, utiliser `/usr/bin/mariadb` à l’avenir
- **Commande** : `php -v`, `php -m`, `php -i | grep -iE 'upload|max_execution|memory_limit|post_max|file_uploads'`, `php -m | grep -iE 'pdo|mysqli|mbstring|intl|gd|imagick|zip|curl|openssl'`, `mysql --version`

*À compléter avec les autres informations (env, distribution, etc.) au fur et à mesure des retours.*

---

*Document généré automatiquement pour servir de référence projet et onboarding rapide.*

## TODO projet (à garder en vue)

- Une fois le projet fonctionnel et stabilisé, automatiser la création de l’environnement utilisateur :
  - Génération et gestion des credentials (SSH, base de données) pour chaque tenant
  - Script de provisioning (création BDD, utilisateur, mot de passe, fichier credentials local)
  - Sécurisation et rotation des accès

## Informations de version MariaDB (phpMyAdmin)

- **version** : 11.4.7-MariaDB
- **version_comment** : MariaDB Server
- **protocol_version** : 10
- **version_compile_os** : Linux
- **version_compile_machine** : x86_64
- **tls_version** : TLSv1.2, TLSv1.3
- **version_ssl_library** : OpenSSL 1.1.1k FIPS 25 Mar 2021
- **version_malloc_library** : jemalloc 5.2.1-0-gea6b3e973b477b8061e0076bb257dbd7...
- **version_source_revision** : 4th3FTPo2118cfcf82107188f2295631193658b2ef94f4f3f
- **wsrep_patch_version** : wsrep_26.22

*Requête exécutée via phpMyAdmin :*

```sql
SHOW VARIABLES LIKE '%version%';
```

## Variables d'environnement pertinentes (env | grep -iE 'php|mysql|path')

- **MODULES_RUN_QUARANTINE** : LD_LIBRARY_PATH LD_PRELOAD
- **MANPATH** : /usr/share/man:
- **MODULEPATH** : /etc/scl/modulefiles:/usr/share/Modules/modulefiles:/etc/modulefiles:/usr/share/modulefiles
- **MODULEPATH_modshare** : /usr/share/Modules/modulefiles:2:/etc/modulefiles:2:/usr/share/modulefiles:2
- **PATH** : /usr/local/cpanel/3rdparty/lib/path-bin:/usr/share/Modules/bin:/usr/local/cpanel/3rdparty/lib/path-bin:/usr/local/jdk/bin:/usr/kerberos/sbin:/usr/kerberos/bin:/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:/usr/X11R6/bin:/usr/local/bin:/usr/X11R6/bin:/root/bin:/opt/bin:/opt/cpanel/composer/bin:/home9/ron2cuba/bin

*Commande exécutée :*

```sh
env | grep -iE 'php|mysql|path'
```

## Informations système (uname -a)

- **OS** : GNU/Linux
- **Hostname** : abricot.o2switch.net
- **Kernel** : 4.18.0-553.8.1.lve.el8.x86_64 (SMP, 4 juillet 2024)
- **Architecture** : x86_64
- **Distribution** : CentOS 8 (CloudLinux, kernel 4.18.x)
- **Commande exécutée** :

```sh
uname -a
```

- **Interprétation** :
  - Serveur mutualisé O2Switch basé sur une distribution Linux compatible Red Hat/CentOS 8 (kernel 4.18.x, LVE = CloudLinux)
  - Architecture 64 bits

## Installation manuelle de Caddy sur O2Switch (mutualisé, CentOS 8/CloudLinux)

### Étapes réalisées (août 2025)

1. Téléchargement du binaire Caddy (Linux x86_64) :

   ```sh
   curl -o caddy -L "https://github.com/caddyserver/caddy/releases/latest/download/caddy_linux_amd64"
   ```

2. Attribution des droits d’exécution :

   ```sh
   chmod +x caddy
   ```

3. Vérification de l’installation :

   ```sh
   ./caddy version
   # Résultat attendu : v2.10.0 h1:...
   ```

**Remarques** :

- Le binaire téléchargé n’est pas une archive : inutile de décompresser.
- L’installation se fait sans droits root, dans le dossier personnel de l’utilisateur.
- Caddy n’est pas disponible via yum/dnf sur l’hébergement mutualisé O2Switch.

*Prochaine étape : configuration d’un Caddyfile et lancement sur un port utilisateur (>1024).*

## Intégration FrankenPHP + Caddy (août 2025)

### Installation de FrankenPHP (binaire utilisateur)

1. Téléchargement et installation :

   ```sh
   curl -Lo frankenphp "https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-amd64"
   chmod +x frankenphp
   ./frankenphp --version
   ```

2. Préparation du Caddyfile pour Symfony + FrankenPHP :

   ```caddyfile
   :8080 {
       root * /home9/ron2cuba/www/public
       php_fastcgi frankenphp:9000
       file_server
   }
   ```

3. Lancement de FrankenPHP en mode FastCGI :

   ```sh
   ./frankenphp run --port=9000 /home9/ron2cuba/www/public
   ```

4. Lancement de Caddy avec le Caddyfile adapté :

   ```sh
   ./caddy run --config ./lenouvel.me/Caddyfile
   ```

**Remarques** :

- Adapter le chemin `/home9/ron2cuba/www/public` selon l’emplacement de ton projet Symfony.
- Les deux processus doivent tourner en parallèle (deux terminaux ou en arrière-plan).
- Documenter toute adaptation ou retour d’expérience dans ce fichier.

## Journal d’intégration Caddy + FrankenPHP (août 2025)

### Étapes réalisées

- Téléchargement et installation du binaire Caddy (v2.10.0) dans le dossier utilisateur.
- Téléchargement du binaire FrankenPHP (v1.9.0) sur poste local, puis transfert SCP vers le serveur mutualisé O2Switch.
- Attribution des droits d’exécution sur les deux binaires.
- Vérification des versions :
  - `./caddy version` → v2.10.0
  - `./frankenphp --version` → v1.9.0 PHP 8.4.11
- Lancement de FrankenPHP en mode FastCGI sur le port 9000 :
  - `./frankenphp php-server --listen :9000 /home9/ron2cuba/www/public`
- Préparation d’un Caddyfile minimal pour servir Symfony via FrankenPHP sur le port 8080.
- Prêt à lancer Caddy pour tester l’accès à l’application.

## TODO – Reprise projet Home Cloud (août 2025)

- Lancer Caddy avec le Caddyfile adapté pour reverse proxy sur le port 8080.
- Tester l’accès à l’application Symfony via http://[adresse_serveur]:8080 (ou tunnel SSH si besoin).
- Vérifier le bon fonctionnement de la chaîne Caddy → FrankenPHP → Symfony.
- Documenter tout retour d’expérience ou adaptation supplémentaire.
- Poursuivre la configuration multi-tenant et la sécurisation.
