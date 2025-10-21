# Home Cloud

## Astuce pour les déploiements futurs

Pour éviter les surprises :

- 🧪 Testez toujours votre `.cpanel.yml` en local :
  - Clonez votre dépôt sur votre ordinateur et lancez les commandes du fichier `.cpanel.yml` manuellement pour vérifier qu’elles fonctionnent.
- 🌱 Utilisez des branches dédiées :
  - Déployez depuis la branche `main` pour plus de contrôle.

---

## Modélisation métier (diagramme de classes)

[![Coverage Status](https://img.shields.io/badge/coverage-80%25-brightgreen)](https://github.com/ronan-develop/home-cloud/actions)

Le projet Home Cloud repose sur une architecture orientée utilisateurs particuliers : chaque utilisateur dispose de son propre espace privé et peut partager des ressources avec d’autres personnes, qu’elles soient ou non inscrites sur la plateforme.

### 1. User

- **Rôle** : utilisateur particulier, propriétaire d’un espace privé.
- **Responsabilités** : gère l’authentification, les informations de connexion et la date de création.

### 2. PrivateSpace

- **Rôle** : espace privé appartenant à un utilisateur.
- **Responsabilités** : contient les ressources, documents ou services propres à l’utilisateur.

### 3. Database

- **Rôle** : représente la base de données dédiée à un espace privé.
- **Responsabilités** : stocke les informations de connexion (nom, DSN, utilisateur) et la date de création.

### 4. File

- **Rôle** : représente un fichier stocké dans l’espace privé d’un utilisateur.
- **Responsabilités** : gère le nom, le chemin, la taille, le type MIME, la date de création et le propriétaire du fichier.

### 5. Share

- **Rôle** : permet à un utilisateur de partager une ressource (fichier ou accès global à l’espace privé) avec d’autres personnes (utilisateurs inscrits ou invités externes).
- **Responsabilités** : gère le lien de partage, l’adresse email de l’invité, la date de création, le niveau d’accès (lecture, modification…), la date d’expiration et le statut (interne/externe).

#### Règles de gestion et cas d’usage du partage

- Un **User** possède un **PrivateSpace**.
- Un **PrivateSpace** utilise une **Database** dédiée.
- Un **PrivateSpace** contient plusieurs **Files**.
- Un **File** peut être partagé via plusieurs **Share** (lien public, invitation email, droits d’accès, expiration).
- Un **PrivateSpace** peut aussi être partagé globalement (accès invité à tout l’espace).
- Un **Share** peut cibler un utilisateur inscrit ou un invité externe (email).
- Les droits d’accès sont définis par **Share** (lecture seule, modification, suppression).
- Les accès partagés peuvent générer des notifications et être suivis (logs).

Le diagramme de classes est maintenu dans le fichier `classes.puml` à la racine du projet (format PlantUML).

---

## Cas d’usage du partage

### 1. Partage par lien public

- L’utilisateur génère un lien unique pour un fichier ou un dossier.
- Le lien peut être protégé par mot de passe et/ou limité dans le temps (expiration automatique).
- Toute personne disposant du lien peut accéder à la ressource selon les droits définis (lecture seule, téléchargement, etc.).

### 2. Partage par invitation email

- L’utilisateur invite une ou plusieurs personnes par email (utilisateurs existants ou externes).
- L’invité reçoit un lien d’accès personnalisé, éventuellement temporaire.
- L’accès peut être révoqué à tout moment par le propriétaire.

### 3. Gestion des droits d’accès

- Pour chaque partage, l’utilisateur définit le niveau d’accès : lecture seule, modification, suppression, etc.
- Les droits sont appliqués au niveau du fichier, du dossier ou de l’espace privé.

### 4. Notifications et suivi

- Le propriétaire reçoit une notification à chaque accès ou téléchargement via un lien partagé.
- Un historique/log des accès partagés est conservé (date, IP, action réalisée).

### 5. Révocation et gestion des partages

- L’utilisateur peut à tout moment désactiver un lien public ou une invitation.
- Les accès sont immédiatement coupés après révocation.

---

## Choix technique backend : Application web Symfony

Pour Home Cloud, l'application web Symfony (interface utilisateur et logique métier) est le cœur du projet. L'API REST via API Platform est optionnelle et activable par instance selon les besoins. Ce choix est motivé par :

- Priorité à l'interface utilisateur intuitive pour la gestion des fichiers, dossiers et partages
- Symfony assure la robustesse pour la gestion HTTP, la sécurité, l'upload et l'authentification
- API Platform disponible pour exposer des endpoints REST si nécessaire (intégration externe, mobile, etc.)
- Large écosystème et support
- Adapté aux contraintes O2Switch (Apache/PHP natif)

**Cas d'usage couverts par l'application web** :

- Upload de fichiers dans l'espace privé de l'utilisateur
- Partage de fichiers ou de dossiers via lien public ou invitation email
- Attribution de droits d'accès fins (lecture, modification, suppression)
- Révocation et suivi des partages
- Accès sécurisé aux ressources pour les membres et les invités externes

API Platform peut être activée ultérieurement pour exposer une API REST ou GraphQL, sans remettre en cause l'architecture

---

## Déploiement par package Composer (lenouvel.me)

Le projet Home Cloud est désormais distribué sous forme de package Composer privé hébergé sur [lenouvel.me](https://lenouvel.me). Chaque sous-domaine correspond à une instance Symfony indépendante, installée et maintenue via Composer.

### Nouveau workflow

- Symfony 7 webapp indépendante par domaine
- Installation et mise à jour via Composer (`create-project` ou `update`)
- Hébergement O2Switch mutualisé (Apache/PHP natif)
- Pas de Docker/Caddy/FrankenPHP

### Installation sur un domaine

```bash
composer create-project lenouvel/home-cloud-mon-espace /home/cloud/mon-espace
```

### Mise à jour du package

```bash
cd /home/cloud/mon-espace
composer update
```

### Avantages

- Maintenance facilitée (update Composer)
- Isolation parfaite entre espaces
- Déploiement rapide sur chaque sous-domaine
- Compatible mutualisé O2Switch

### Notes importantes

- Le package privé doit être référencé dans le `composer.json` (voir documentation lenouvel.me)
- Les credentials d’accès au dépôt privé Composer ne doivent jamais être commités
- Toute configuration spécifique (base, domaine, etc.) se fait via `.env.local` (jamais `.env.test`)

### Documentation détaillée

- Voir `.github/projet-context.md` et la documentation lenouvel.me pour la configuration du dépôt privé Composer

---

---

## Stack serveur imposée

> ⚠️ L’hébergement O2Switch mutualisé n’autorise que la stack Apache/PHP natif. L’utilisation de serveurs applicatifs utilisateurs (Caddy, FrankenPHP, etc.) est strictement impossible. Toute la configuration et le déploiement doivent être adaptés à cette contrainte.

---

## Démarrage local de l’application

Pour développer ou tester Home Cloud en local, utilise le serveur interne PHP (compatible sur tous les environnements) :

```bash
php -S localhost:8000 -t public
```

Accède ensuite à [http://localhost:8000](http://localhost:8000) pour utiliser l’interface web Home Cloud.

Cette méthode fonctionne partout, même si `symfony serve` échoue ou que PHP-FPM n’est pas disponible.

---

## Astuce pour consulter les logs de déploiement en temps réel

Pour suivre l’exécution du déploiement sur O2Switch et diagnostiquer rapidement un problème, connectez-vous en SSH sur le serveur puis lancez :

```sh
ssh -p 22 ron2cuba@abricot.o2switch.net
# Puis, une fois connecté :
tail -f /home9/ron2cuba/.cpanel/deployment/logs/deployment-*.log
```

- Cette commande affiche en direct les logs de tous les déploiements cPanel.
- Pratique pour vérifier le déroulement, repérer une erreur ou valider la fin du process.

---

## Tests d’intégration et validation ORM

- Un test d’intégration (`tests/Entity/UserPrivateSpaceTest.php`) valide la création, la persistance et la relation bidirectionnelle entre User et PrivateSpace.
- La configuration `.env.test` permet d’utiliser une base MariaDB locale dédiée aux tests.
- La migration Doctrine est appliquée sur la base de test pour garantir la cohérence du schéma.
- 4 assertions vérifient la cohérence ORM et l’accès bidirectionnel entre User et PrivateSpace.

---

## Endpoints principaux

👉 [Voir la liste complète des endpoints dans `api_endpoints.md`](./api_endpoints.md)

---

## Bonnes pratiques API Platform

- Privilégier l’exposition des endpoints via API Platform pour bénéficier de la documentation Swagger/OpenAPI, du typage et de la maintenabilité.
- Utiliser des DTOs et providers pour les endpoints informatifs ou custom (accueil, healthcheck, etc.).
- Synchroniser la documentation métier et technique à chaque évolution majeure.

---

## Couverture de test automatisée

Pour générer la couverture de test :

```sh
bin/phpunit-coverage --coverage-text
```

Le script active automatiquement Xdebug coverage pour faciliter la CI et la reproductibilité.

---

## Workflow de développement et déploiement O2Switch

### 1. Développement local

- Travaille sur une branche dédiée.
- Commits réguliers, messages conformes à la convention (voir [CONVENTION_COMMITS.md](CONVENTION_COMMITS.md)).

### 2. Création de Pull Request (PR)

- Ouvre une PR sur GitHub pour chaque fonctionnalité/correction.
- Respecte la convention de titre et de description (voir [CONVENTION_PR.md](CONVENTION_PR.md)).
- Merge uniquement après validation/review.

### 3. Déploiement

- Après merge sur `main`, push sur GitHub :

  ```bash
  git push origin main
  ```

- Synchronise ensuite le dépôt O2Switch via l’interface cPanel :
  - Va dans cPanel > Git™ Version Control > ton dépôt > clique sur “Update from Remote” pour rapatrier les changements depuis GitHub.
  - Le déploiement automatique s’exécutera alors via le `.cpanel.yml` versionné.

- Le fichier `.cpanel.yml` doit être à jour et versionné.
- Vérifie le déploiement dans l’interface cPanel.

### 4. Dépôt de secours

- Le repo O2Switch sert aussi de backup :
  `ssh://ron2cuba@ron2cuba.odns.fr/home9/ron2cuba/repositories/home-cloud`

---

## Historique des tests

- Voir la liste complète dans [TESTS_HISTORIQUE.md](TESTS_HISTORIQUE.md)

---

## Liens utiles

- [Convention de commits](CONVENTION_COMMITS.md)
- [Convention de PR](CONVENTION_PR.md)

---

Prochaine étape : modéliser techniquement ces cas d’usage (API, entités, flux) et enrichir la documentation technique.
