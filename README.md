# Home Cloud

[![Coverage Status](https://img.shields.io/badge/coverage-80%25-brightgreen)](https://github.com/ronan-develop/home-cloud/actions)

## Modélisation métier (diagramme de classes)

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

## Tests d’intégration et validation ORM

- Un test d’intégration (`tests/Entity/UserPrivateSpaceTest.php`) valide la création, la persistance et la relation bidirectionnelle entre User et PrivateSpace.
- La configuration `.env.test` permet d’utiliser une base MariaDB locale dédiée aux tests.
- La migration Doctrine est appliquée sur la base de test pour garantir la cohérence du schéma.
- 4 assertions vérifient la cohérence ORM et l’accès bidirectionnel entre User et PrivateSpace.

---

## Stratégie ISO base de test / production

Pour garantir la robustesse et la reproductibilité des tests, la base de test utilisée (SQLite) est synchronisée à chaque exécution avec le schéma Doctrine de la base MariaDB de production :

- Le schéma Doctrine est appliqué à la base SQLite avant chaque run de test (`doctrine:schema:update` ou migrations).
- Les tests purgent et injectent systématiquement les mêmes données de référence (utilisateur, private space, etc.) avant chaque test.
- Les contraintes de structure (colonnes, types, index) sont vérifiées pour garantir l’alignement avec la prod.
- Les tests d’authentification, de persistance et de logique métier sont donc valides et reproductibles, quelle que soit la base sous-jacente.

**Limite** : certaines différences SQL natives (types, index, contraintes) peuvent exister entre SQLite et MariaDB ; elles sont documentées et surveillées lors des migrations.

---

## Endpoints principaux

### Endpoint d’accueil documenté

- **GET /api/info**
  - Exposé via API Platform (DTO InfoApiOutput + provider)
  - Retourne : message d’accueil, version, endpoint login, info d’authentification
  - Documenté dans Swagger/OpenAPI
  - Exemple de réponse :

    ```json
    {
      "@context": "/api/contexts/InfoApiOutput",
      "@id": "/api/info",
      "@type": "InfoApiOutput",
      "message": "Bienvenue sur l’API Home Cloud.",
      "version": "1.0.0",
      "login_endpoint": "/api/login",
      "info": "Authentifiez-vous via POST /api/login avec vos credentials (email/username + password)."
    }
    ```

- **GET /api**
  - Contrôleur Symfony classique (non documenté Swagger)
  - Retourne un message d’accueil simple (legacy)

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

## Cas d'usage utilisateur

L'API Home Cloud permet à chaque utilisateur de :

- Se connecter via JWT
- Uploader des fichiers (photo, vidéo, tout type)
- Mettre à jour ou supprimer ses fichiers
- Lister et trier ses fichiers par date de création

Voir la section TODO.md pour le détail des endpoints et règles métier.

---

## Développement et tests automatisés avec Docker (PHP 8.3 + SQLite)

Pour garantir la compatibilité avec la prod O2Switch (PHP 8.3, extensions natives), le projet fournit un environnement Docker dédié au développement et aux tests automatisés.

### Utilisation rapide

1. **Build de l’image Docker (si besoin)**

   ```sh
   docker build -f Docker/Dockerfile.php83-sqlite -t php83-sqlite-dev .
   ```

2. **Lancement d’un shell dans le conteneur**

   ```sh
   docker run --rm -it -v "$PWD":/app -w /app php83-sqlite-dev bash
   ```

3. **Automatisation des tests**
   Utilise le script fourni pour exécuter les tests ou toute commande Symfony/Composer dans l’environnement Docker :

   ```sh
   ./docker-test.sh
   # ou pour une commande personnalisée
   ./docker-test.sh "php bin/console doctrine:schema:validate"
   ```

### À quoi sert ce Dockerfile ?

- Environnement isolé pour dev/test local (PHP 8.3, SQLite, Xdebug, Composer)
- Aucune dépendance système requise sur ta machine (hors Docker)
- Ne pas utiliser pour la production (O2Switch = Apache/PHP natif, pas Docker)

### Bonnes pratiques

- Développe en local, utilise Docker pour les tests et la validation finale
- Documente toute modification de workflow dans ce README
- Pense à committer le Dockerfile, le script et à créer une Pull Request pour la revue

### Script d’automatisation

- `docker-test.sh` : build auto, lancement du conteneur, exécution des tests ou commandes Symfony/Composer
- Rends-le exécutable : `chmod +x docker-test.sh`

---

Prochaine étape : modéliser techniquement ces cas d’usage (API, entités, flux) et enrichir la documentation technique.
