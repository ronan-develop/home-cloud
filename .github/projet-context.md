# Contexte et Objectifs du projet Home Cloud

## Objectif principal

Créer une solution d’hébergement personnel (type cloud privé) pour stocker, partager et synchroniser photos et documents, sans dépendre des services Apple, Google, etc.

## Architecture cible

- Application API Platform (Symfony, PHP, version LTS 7.3.2 à ce jour) hébergée chez O2Switch (hébergement mutualisé, pas de Docker possible)
- API exposée en REST (et optionnellement GraphQL) via API Platform : toute la logique d’exposition, la documentation OpenAPI, la gestion des endpoints et des ressources sont assurées par API Platform.
- Application conçue en mode multi-tenant : chaque sous-domaine (ex : ronan.lenouvel.me, elea.lenouvel.me) correspond à un espace privé et isolé pour un utilisateur ou un groupe, tout en partageant le même cœur applicatif et la même base de code.
- Chaque utilisateur disposera de sa propre base de données MySQL dédiée, afin de garantir une isolation stricte des données et d’éviter tout mélange entre les espaces privés.
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
5. Une fois connecté, tu peux utiliser la console pour manipuler les fichiers, lancer des commandes Symfony/API Platform, composer, etc.

## Fonctionnalités attendues

- Stockage et partage de fichiers/photos
- Interface web intuitive
- Gestion fine des utilisateurs et permissions
- Synchronisation multi-appareils
- Support multi-tenant (sous-domaines)
- API RESTful exposée via API Platform (documentation OpenAPI intégrée)

## Pourquoi API Platform (Symfony) ?

- API Platform permet de générer rapidement une API RESTful (et GraphQL si besoin) robuste, documentée (OpenAPI), sécurisée et compatible avec les contraintes O2Switch.
- Symfony assure la robustesse pour la gestion HTTP, la sécurité, l’upload, et l’intégration avec API Platform.
- Large écosystème et support.

## Points d’attention

- Pas d’accès root, pas de customisation serveur profonde
- Pas de containers, pas de reverse proxy custom
- Tout doit fonctionner dans les limites d’un mutualisé PHP classique
- Toute la logique API doit être implémentée via API Platform (ressources, opérations, filtres, sécurité)

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

## TODO automatisation et sécurité (à garder en vue)

- Une fois le projet fonctionnel et stabilisé, automatiser la création de l’environnement utilisateur :
  - Génération et gestion des credentials (SSH, base de données) pour chaque tenant
  - Script de provisioning (création BDD, utilisateur, mot de passe, fichier credentials local)
  - Sécurisation et rotation des accès

## Journal des actions réalisées

- **Août 2025**
  - Migration du dépôt GitLab vers GitHub (sécurité, traçabilité)
  - Purge complète de l’historique git pour supprimer les secrets accidentellement commités (.env, credentials, tokens)
  - Synchronisation et enrichissement de la documentation métier et technique (README, api_endpoints.md, classes.puml)
  - Ajout d’une section détaillée sur l’architecture multi-tenant par sous-domaine (isolation, sécurité, filtrage applicatif)
  - Ajout et harmonisation des instructions IA (rappel de commit, conventions, sécurité)
  - Refonte du .gitignore pour ignorer tous les fichiers sensibles
  - Documentation de la procédure d’installation manuelle de Caddy et FrankenPHP sur O2Switch (mutualisé)
  - Abandon de la stack Caddy/FrankenPHP en mode serveur au profit d’Apache/PHP natif (limites O2Switch)
  - Modélisation métier complète (diagramme de classes, cas d’usage de partage, gestion des droits, logs)
  - Création de l’entité User (modélisation initiale), génération et application de la première migration Doctrine (table user)
  - Configuration de la connexion MariaDB locale via un utilisateur dédié (`ronan`), gestion des droits et adaptation du fichier `.env.local`
  - Documentation de la procédure dans la PR associée et rappel de la synchronisation avec `classes.puml` et `.github/projet-context.md`
  - Création de la branche feat/private-space pour la modélisation de l’entité PrivateSpace
  - Génération/adaptation de la classe PrivateSpace (id, name, description, createdAt) dans src/Entity/PrivateSpace.php
  - Synchronisation du diagramme de classes (classes.puml) avec la nouvelle entité
  - Préparation à la migration Doctrine et à la création des relations (User, Database)
  - Ajout de la relation bidirectionnelle OneToOne User <-> PrivateSpace (accès $privateSpace->getUser()), synchronisation du diagramme et documentation. Rappel : commit et PR à chaque étape structurante.
  - Ajout d’un test d’intégration pour valider la persistance et la relation bidirectionnelle User <-> PrivateSpace (tests/Entity/UserPrivateSpaceTest.php)
  - Configuration complète de .env.test pour la base MariaDB de test locale
  - Migration Doctrine appliquée sur la base de test, schéma synchronisé
  - 4 assertions validant la cohérence ORM et l’accès bidirectionnel
  - Rappel : commit et Pull Request à chaque étape structurante, documentation à synchroniser dans README.md et .github/projet-context.md

  - Rappel : proposer systématiquement à l’utilisateur de lancer les commandes utiles (console Symfony, migrations, tests, etc.) à chaque étape technique pour faciliter la validation et la reproductibilité.

  - Ajout d’un endpoint d’accueil `/api/info` exposé via API Platform (DTO InfoApiOutput + provider InfoApiProvider)
    - Endpoint documenté dans Swagger/OpenAPI, typé, maintenable, sans entité Doctrine
    - Correction de la config API Platform pour inclure explicitement `src/Dto` dans le mapping
    - Documentation du contrôleur d’accueil classique (`ApiHomeController`) pour clarifier la différence avec la ressource officielle
    - Rappel : commit et Pull Request à chaque étape structurante, synchronisation de la doc technique

- **Juillet 2025**
  - Initialisation du projet Home Cloud (structure Symfony, API Platform, configuration O2Switch)
  - Définition des premiers endpoints API et des entités principales (User, PrivateSpace, File, Share, AccessRight, AccessLog)

## TODO projet (à garder en vue)

- Une fois le projet fonctionnel et stabilisé, automatiser la création de l’environnement utilisateur :
  - Génération et gestion des credentials (SSH, base de données) pour chaque tenant
  - Script de provisioning (création BDD, utilisateur, mot de passe, fichier credentials local)
  - Sécurisation et rotation des accès

## Informations détaillées de version MariaDB (phpMyAdmin)

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

## Multi-tenant par sous-domaine

- Chaque sous-domaine (ex : elea.lenouvel.me, ronan.lenouvel.me) correspond à un espace privé isolé, avec sa propre racine documentaire et (optionnellement) sa propre base de données.
- L’application détecte le sous-domaine courant et filtre toutes les données côté applicatif (Symfony) pour garantir l’isolation stricte.
- Aucune donnée d’un espace privé ne doit être accessible depuis un autre sous-domaine.
- La sécurité et la confidentialité sont assurées par ce découpage logique et applicatif.

*À compléter avec les autres informations (env, distribution, etc.) au fur et à mesure des retours.*

---

*Document généré automatiquement pour servir de référence projet et onboarding rapide.*

## Stack serveur imposée par O2Switch

- Seule la stack Apache/PHP natif est supportée sur l’hébergement mutualisé O2Switch.
- Impossible d’exécuter Caddy, FrankenPHP ou tout autre serveur HTTP utilisateur.
- Toute la documentation, les scripts et la configuration doivent être adaptés à cette contrainte.

## Convention de nommage des bases de données utilisateurs

- Format : `ron2cuba_hc_<username>`
  - `ron2cuba` : préfixe imposé par O2Switch
  - `hc` : identifiant du projet Home Cloud
  - `<username>` : identifiant unique de l’utilisateur (ex : ronan, elea, yannick)
- Exemple pour toi : `ron2cuba_hc_ronan`
- À appliquer systématiquement pour chaque nouvel utilisateur

## Cas d'usage utilisateur (synchronisation)

- Connexion sécurisée (JWT)
- Upload de fichiers (photo, vidéo, tout type)
- Update/delete de fichiers
- Tri des fichiers par date de création

Endpoints principaux : voir README.md et TODO.md

# 🔒 Bonnes pratiques Firewall & Auth API Platform + JWT (Home Cloud)

- Toujours placer le firewall `login` (pour `/api/login_check`) **avant** le firewall `api` (JWT) dans `security.yaml`.
- Le firewall `login` doit matcher uniquement `/api/login_check` et utiliser `json_login` (ou le contrôleur LexikJWT).
- Le firewall `api` doit matcher `^/api` et utiliser `jwt: ~`.
- Le firewall `main` doit être sans pattern, en dernier, pour couvrir le reste du site (form_login ou json_login si besoin).
- Ne jamais utiliser `form_login` pour une API, préférer `json_login` ou LexikJWT.
- Les `access_control` doivent autoriser l’anonyme sur `/api/login_check` et exiger l’authentification sur `/api`.
- L’ordre des firewalls est **critique** : Symfony n’active qu’un seul firewall par requête, le premier qui matche le pattern.

> Pour toute évolution de la sécurité, commit + PR obligatoire pour garantir la traçabilité et la revue.
