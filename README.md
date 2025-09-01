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

## Choix technique backend : API REST

Pour Home Cloud, l’API backend sera exposée en REST via API Platform. Ce choix est motivé par :

- Simplicité d’intégration avec tous les clients (PWA, mobile, desktop)
- Standardisation des opérations CRUD (upload, partage, suppression de fichiers)
- Facilité de sécurisation (authentification, droits d’accès, gestion des tokens)
- Documentation automatique (OpenAPI/Swagger)
- Compatibilité avec les outils de test et d’intégration (Postman, Insomnia, etc.)
- Facilité de gestion des uploads (multipart/form-data, endpoints dédiés)
- Gestion native de la pagination, des filtres, de la validation et des relations

**Cas d’usage couverts par l’API REST** :

- Upload de fichiers dans l’espace privé de l’utilisateur
- Partage de fichiers ou de dossiers via lien public ou invitation email
- Attribution de droits d’accès fins (lecture, modification, suppression)
- Révocation et suivi des partages
- Accès sécurisé aux ressources pour les membres et les invités externes

API Platform permettra d’ajouter GraphQL plus tard si besoin, sans remettre en cause l’architecture.

---

## Architecture multi-tenant par sous-domaine

Chaque sous-domaine (ex : elea.lenouvel.me, ronan.lenouvel.me, yannick.lenouvel.me) correspond à un espace privé isolé pour un utilisateur ou un groupe. L’application détecte le sous-domaine courant et filtre toutes les données (fichiers, partages, logs, etc.) pour garantir l’isolation stricte entre les espaces privés.

- Un `User` possède un `PrivateSpace` (relation 1:1)
- Chaque espace privé est physiquement séparé (racine documentaire dédiée, base de données dédiée ou schéma logique)
- Aucune donnée d’un espace ne doit être accessible depuis un autre sous-domaine
- Toute la logique multi-tenant est gérée côté applicatif (Symfony)

Cette architecture garantit la confidentialité, la sécurité et la scalabilité du service Home Cloud.

---

## Stack serveur imposée

> ⚠️ L’hébergement O2Switch mutualisé n’autorise que la stack Apache/PHP natif. L’utilisation de serveurs applicatifs utilisateurs (Caddy, FrankenPHP, etc.) est strictement impossible. Toute la configuration et le déploiement doivent être adaptés à cette contrainte.

---

## Démarrage local de l’API

Pour développer ou tester l’API en local, utilise le serveur interne PHP (recommandé sur tous les environnements) :

```sh
php -S localhost:8000 -t public
```

- Accède ensuite à [http://localhost:8000/api](http://localhost:8000/api) pour voir la documentation OpenAPI générée par API Platform.
- Cette méthode fonctionne partout, même si `symfony serve` échoue ou que PHP-FPM n’est pas disponible.

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

## Phase de refonte tests & migrations (septembre 2025)

### Objectif

Garantir une isolation stricte et la robustesse des tests API Platform, en corrigeant les problèmes d’intégrité liés à la relation OneToOne entre User et PrivateSpace.

### Actions réalisées

- **Purge complète des migrations** : suppression de toutes les migrations existantes et génération d’une migration unique alignée sur le schéma actuel des entités.
- **Correction des fixtures** : chaque User n’a plus qu’un seul PrivateSpace associé (respect strict du OneToOne), harmonisation des références dans Share/AccessLog.
- **Refactor des tests CRUD PrivateSpace** : chaque test crée dynamiquement un nouvel utilisateur avant de créer un PrivateSpace, évitant toute violation d’unicité.
- **Chargement des fixtures** : validé sans erreur, la base de test est toujours cohérente.
- **Isolation maximale** : chaque test API Platform réinitialise la base (drop/create schema + fixtures) pour garantir reproductibilité et absence de pollution d’état.

### Pourquoi ce choix ?

- L’isolation transactionnelle ne fonctionne pas avec le kernel HTTP/API Platform (voir doc officielle).
- Réinitialiser la base avant chaque test est la seule méthode fiable pour garantir l’indépendance et la reproductibilité des tests fonctionnels API.
- Ce pattern est recommandé par la doc Symfony/API Platform pour tous les tests dépendant des données.

### Résultat

- Suite de tests API Platform 100% verte (hors tests d’intégration non corrigés)
- Plus aucune violation d’unicité sur la relation User <-> PrivateSpace
- Base de code et fixtures prêtes pour l’évolution multi-tenant et la montée en charge

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

## Stratégie de test

La stratégie complète de test (isolation, organisation, pattern d’initialisation, bonnes pratiques) est détaillée dans le fichier [`tests/STRATEGIE_TESTS.md`](./tests/STRATEGIE_TESTS.md).

- Objectif : garantir robustesse, reproductibilité et isolation stricte de tous les tests (unitaires, intégration, API Platform)
- Pattern d’isolation API Platform : reset complet de la base et rechargement des fixtures avant chaque test fonctionnel
- Organisation des tests, conventions et liens utiles dans le fichier dédié

👉 [Voir la stratégie de test complète](./tests/STRATEGIE_TESTS.md)

---

Prochaine étape : modéliser techniquement ces cas d’usage (API, entités, flux) et enrichir la documentation technique.
