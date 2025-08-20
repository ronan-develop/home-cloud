---
applyTo: '**'
---

# Instructions IA – Contexte projet Home Cloud

## Résumé du contexte

- Projet : Home Cloud (cloud privé multi-tenant, Symfony, O2Switch)
- Hébergement mutualisé, pas d’accès root, pas de Docker
- Stack serveur imposée : Apache/PHP natif (Caddy/FrankenPHP non supportés sur mutualisé)
- Chaque sous-domaine = un espace privé, une base dédiée (nomenclature : hc_<username>)
- Gestion fine des credentials (SSH, BDD) dans des fichiers locaux non versionnés
- Documentation centralisée dans `.github/projet-context.md`
- Distribution serveur : CentOS 8 (CloudLinux, kernel 4.18.x) – cf. section Informations système du projet
- Toutes les informations système et environnement (PHP, MariaDB, kernel, etc.) sont synchronisées avec `.github/projet-context.md`

## Points clés à retenir

- Ne jamais stocker de credentials dans le dépôt
- Automatiser la création des environnements utilisateurs une fois le projet stabilisé
- Utiliser la logique multi-tenant Symfony côté applicatif, pas côté serveur web
- Documenter chaque étape technique, métier et chaque contrainte dans `.github/`
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur
- Privilégier la documentation métier (README, diagrammes, cas d’usage) et la traçabilité des choix techniques

## API & Modélisation

- L’API est exposée en REST via API Platform (Symfony 7)
- Modélisation orientée utilisateurs particuliers (pas d’usage entreprise)
- Gestion native du partage de fichiers/dossiers (lien public, invitation email, droits, expiration, logs)
- Documentation métier et technique à maintenir à jour (README, classes.puml, api_endpoints.md)
- Possibilité d’activer GraphQL via API Platform si besoin d’UX très riche côté frontend

## TODO IA

- Garder en mémoire la roadmap d’automatisation (provisioning, rotation credentials)
- S’assurer que toute nouvelle doc ou script respecte la sécurité, la maintenabilité et la compatibilité mutualisé
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur (ex : changement de distribution, upgrade PHP/MariaDB, modification des contraintes O2Switch)

---

# Bonnes pratiques de tests API Platform/Symfony (Home Cloud)

- Utiliser `ApiTestCase` pour tous les tests d’API (pas de requêtes réseau, accès direct au kernel Symfony).
- Privilégier les assertions API Platform : `assertJsonEquals`, `assertJsonContains`, `assertMatchesJsonSchema`, etc.
- Factoriser la récupération du token JWT et la création de clients authentifiés dans une classe de base de tests (ex : `AbstractApiTest`).
- Couvrir l’accès anonyme, l’authentification, et les droits d’accès (401, 403, etc.) dans les tests.
- S’assurer que la base de test MariaDB est bien utilisée lors des tests (ISO prod).
- Intégrer la suite de tests dans la CI/CD (GitHub Actions), avec build Docker et exécution sur MariaDB.
- Synchroniser `.env.test.example` à chaque évolution de la config de test.
- Commit + PR à chaque ajout ou modification de tests pour la traçabilité.

---

# Authentification JWT avec Symfony (LexikJWTAuthenticationBundle)

- Installer le bundle :
  ```sh
  composer require lexik/jwt-authentication-bundle
  ```
- Générer les clés :
  ```sh
  php bin/console lexik:jwt:generate-keypair
  # ou via Docker, voir doc projet
  ```
- Ne jamais versionner les clés privées/publics (config/jwt/ dans .gitignore)
- Configurer le provider sur la propriété `email` de l'entité User
- Configurer le firewall principal avec :
  - `json_login` (pour POST /auth ou /login)
  - `jwt: ~` (pour sécuriser les routes après login)
  - `success_handler` et `failure_handler` Lexik pour la gestion des réponses
- Exemple de bloc dans `security.yaml` :
  ```yaml
  firewalls:
    main:
      stateless: true
      provider: users
      json_login:
        check_path: /auth
        username_path: email
        password_path: password
        success_handler: lexik_jwt_authentication.handler.authentication_success
        failure_handler: lexik_jwt_authentication.handler.authentication_failure
      jwt: ~
  ```
- Ajouter la route `/auth` dans `routes.yaml` si besoin
- Documenter l’authentification dans Swagger/OpenAPI via `api_platform.yaml` :
  ```yaml
  api_platform:
    swagger:
      api_keys:
        JWT:
          name: Authorization
          type: header
  ```
- Pour les tests :
  - Utiliser `ApiTestCase` et injecter un utilisateur de test avec un mot de passe hashé
  - Récupérer le token via un POST `/auth` puis l’utiliser dans les headers `Authorization: Bearer <token>`
  - Pour accélérer les tests, surcharger le hasher en test (ex : algo md5)
- Toujours commit + PR à chaque évolution de la config de sécurité ou des tests d’authentification.

ℹ️ Privilégier OIDC pour les projets nécessitant une interopérabilité forte ou une scalabilité accrue.

---

# Validation des données avec API Platform/Symfony

- Utiliser les contraintes Symfony (`Assert\*`) directement dans les entités annotées `#[ApiResource]`.
- Les erreurs de validation sont retournées en 422 avec la liste des violations (format JSON-LD par défaut).
- Pour des règles avancées, créer des contraintes personnalisées (voir exemple MinimalProperties).
- Utiliser les validation groups pour adapter les règles selon l’opération (POST, PUT, etc.) :
  - `#[ApiResource(validationContext: ['groups' => ['Default', 'postValidation']])]`
  - Ou par opération : `#[Post(validationContext: ['groups' => ['postValidation']])]`
- Les groupes peuvent être dynamiques via callable ou service (ex : selon le rôle de l’utilisateur).
- Pour accélérer les tests, surcharger le hasher en test (ex : algo md5).
- Les erreurs de dénormalisation (type, format) sont aussi retournées en 422 si `collectDenormalizationErrors` est activé.
- Pour les relations toMany, utiliser `#[Assert\Valid]` sur le getter retournant un tableau.
- API Platform génère automatiquement les restrictions de schéma OpenAPI à partir des contraintes Symfony.
- Commit + PR à chaque évolution de la validation ou des entités pour la traçabilité.

---

# Sécurité API Platform/Symfony

- Utiliser les expressions `security` et `securityPostDenormalize` dans `#[ApiResource]` et sur chaque opération pour contrôler l’accès (ex : `is_granted('ROLE_USER')`, `object.owner == user`).
- Privilégier les voters pour la logique métier complexe (ex : `is_granted('BOOK_EDIT', object)`).
- Personnaliser les messages d’erreur avec `securityMessage` et `securityPostDenormalizeMessage`.
- Pour filtrer les collections selon l’utilisateur, utiliser une extension Doctrine (pas une expression security sur la collection).
- Pour désactiver une opération, ne pas la déclarer dans l’ApiResource.
- Pour changer dynamiquement les groupes de sérialisation selon l’utilisateur, adapter le Serializer context.
- Commit + PR à chaque évolution de la sécurité ou des règles d’accès pour la traçabilité.

---

# Debug avec Xdebug et Docker (Symfony/API Platform)

- Xdebug est inclus par défaut dans la distribution API Platform.
- Pour activer Xdebug dans Docker :
  ```sh
  XDEBUG_MODE=debug XDEBUG_SESSION=1 docker compose up --wait
  ```
- Pour VS Code, ajoute ce bloc dans `.vscode/launch.json` :
  ```json
  {
    "version": "0.2.0",
    "configurations": [
      {
        "name": "Listen for Xdebug",
        "type": "php",
        "request": "launch",
        "port": 9003,
        "log": true,
        "pathMappings": {
          "/app": "${workspaceFolder}/api"
        }
      }
    ]
  }
  ```
- Sur Linux, utilise l’IP locale de ta machine pour `client_host` si `host.docker.internal` ne fonctionne pas.
- Pour vérifier l’installation :
  ```sh
  docker compose exec php php --version
  # ...doit afficher "with Xdebug v..."
  ```
- Pour le debug CLI (console/tests) :
  ```sh
  XDEBUG_SESSION=1 PHP_IDE_CONFIG="serverName=api" php bin/console ...
  ```
- Pense à commit toute configuration de debug utile à l’équipe (ex : launch.json).

---

# Intégration Symfony Messenger (CQRS, async, handler, input object)

- Installer Messenger :
  ```sh
  composer require symfony/messenger
  ```
- Pour une ressource asynchrone, ajouter `messenger: true` sur l’opération (ex : POST) dans `#[ApiResource]`.
- Créer un handler avec `#[AsMessageHandler]` pour traiter la ressource ou l’input.
- Pour traiter un input spécifique (ex : reset password), utiliser `messenger: 'input'` et un DTO dédié.
- Pour un POST async, configurer l’opération avec `output: false` et `status: 202` pour indiquer que le traitement est différé.
- Pour différencier les suppressions, vérifier la présence du RemoveStamp dans le middleware Messenger.
- Commit + PR à chaque ajout de handler, DTO ou modification de la config Messenger pour la traçabilité.

---

# Upload de fichiers avec API Platform et VichUploaderBundle

- Installer le bundle :
  ```sh
  composer require vich/uploader-bundle
  ```
- Activer le format multipart globalement ou par opération dans `api_platform.yaml` :
  ```yaml
  api_platform:
    formats:
      jsonld: ['application/ld+json']
      multipart: ['multipart/form-data']
  ```
- Configurer `vich_uploader.yaml` pour le mapping `media_object` (upload dans `public/media`).
- Créer une entité `MediaObject` annotée `#[Vich\Uploadable]` et `#[ApiResource(..., operations: [Post(inputFormats: ['multipart' => ['multipart/form-data']])])]`.
- Utiliser un normalizer pour exposer l’URL du fichier (`contentUrl`).
- Créer un decoder multipart si besoin pour la désérialisation.
- Pour lier un fichier à une autre ressource (ex : Book), ajouter une relation ManyToOne vers `MediaObject` ou un champ `file` avec `Vich\UploadableField`.
- Pour les tests, utiliser `ApiTestCase` et `UploadedFile` pour simuler l’upload.
- Commit + PR à chaque ajout de ressource, mapping ou test d’upload pour la traçabilité.

---

# Opérations custom et controllers API Platform

- Privilégier les action classes (pattern ADR) plutôt que les contrôleurs Symfony classiques.
- Les actions sont autowirées : tout service peut être injecté par le constructeur.
- Déclarer l’opération custom dans l’attribut `#[ApiResource(operations: [...])]` avec `controller`, `uriTemplate`, `name`, `method`.
- Pour bypasser la récupération automatique de l’entité, utiliser `read: false` dans l’opération.
- Pour documenter l’opération, configurer `normalizationContext`/`denormalizationContext`/OpenAPI sur l’opération.
- Utiliser `PlaceholderAction` si aucune logique custom n’est nécessaire.
- Commit + PR à chaque ajout ou modification d’opération custom pour la traçabilité.

---

# Documentation API : Swagger & NelmioApiDocBundle

- Privilégier le support Swagger/OpenAPI natif d’API Platform pour la documentation interactive.
- Pour les projets existants ou besoins spécifiques, NelmioApiDocBundle 3+ est compatible avec API Platform (activer `enable_nelmio_api_doc: true` dans `api_platform.yaml`).
- Exemple de configuration :
  ```yaml
  api_platform:
    enable_nelmio_api_doc: true

  nelmio_api_doc:
    sandbox:
      accept_type: 'application/json'
      body_format:
        formats: ['json']
        default_format: 'json'
      request_format:
        formats:
          json: 'application/json'
  ```
- Attention : la sandbox Nelmio ne gère pas les tableaux JSON imbriqués (limitation connue).
- Commit + PR à chaque évolution de la documentation API pour la traçabilité.

---

# Utilisation des fixtures avec DoctrineFixturesBundle (Home Cloud)

- Les fixtures permettent de charger des jeux de données de test/démo en base (utilisateurs, espaces privés, etc.) pour le développement et les tests automatisés.
- Compatible avec toutes les bases supportées par Doctrine ORM (MySQL/MariaDB, PostgreSQL, SQLite…)
- Installation :
  ```sh
  composer require --dev orm-fixtures
  # ou
  composer require --dev doctrine/doctrine-fixtures-bundle
  ```
- Crée une classe dans `src/DataFixtures/` qui étend `Fixture`.
- Utilise l’injection de dépendance pour accéder à des services (ex : hasher de mot de passe).
- Exemple pour un utilisateur :
  ```php
  class UserFixture extends Fixture
  {
      public function __construct(private UserPasswordHasherInterface $hasher) {}
      public function load(ObjectManager $manager): void
      {
          $user = new User();
          $user->setUsername('demo');
          $user->setPassword($this->hasher->hashPassword($user, 'password123'));
          // ...autres champs...
          $manager->persist($user);
          $manager->flush();
      }
  }
  ```
- Chargement des fixtures (purge la base par défaut) :
  ```sh
  php bin/console doctrine:fixtures:load --env=test --purge-with-truncate
  ```
- Utilise `addReference()`/`getReference()` pour partager des entités entre fixtures.
- Implémente `DependentFixtureInterface` pour forcer l’ordre de chargement.
- Implémente `FixtureGroupInterface` pour charger des groupes spécifiques.
- Toujours hasher les mots de passe via le service Symfony, jamais à la main.
- Préférer des fixtures modulaires (User, PrivateSpace, etc.) pour la réutilisabilité.
- Documenter chaque fixture et son usage dans `.github/projet-context.md`.
- Synchroniser les données de test avec les besoins métier/API (ex : utilisateurs actifs, rôles, espaces privés…).

---

# Password Hashing and Verification (Symfony)

## Configuration du Password Hasher

- Utilise le composant PasswordHasher de Symfony pour stocker les mots de passe de façon sécurisée.
- Installation :
  ```sh
  composer require symfony/password-hasher
  ```
- Configuration recommandée dans `config/packages/security.yaml` :
  ```yaml
  security:
      password_hashers:
          # Pour la classe User (et enfants)
          App\Entity\User: 'auto'
          # Pour tous les PasswordAuthenticatedUserInterface
          Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
              algorithm: 'auto'
              cost: 15
  ```
- L’algorithme `auto` sélectionne le plus sécurisé disponible (ex : bcrypt, sodium).
- Pour accélérer les tests, configure un hasher plus rapide en test :
  ```yaml
  when@test:
      security:
          password_hashers:
              App\Entity\User:
                  algorithm: auto
                  cost: 4 # Bcrypt minimum
                  time_cost: 3 # Argon minimum
                  memory_cost: 10 # Argon minimum
  ```

## Hashage et vérification du mot de passe

- Utilise `UserPasswordHasherInterface` pour hasher et vérifier les mots de passe :
  ```php
  $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
  $user->setPassword($hashedPassword);
  // Vérification
  $passwordHasher->isPasswordValid($user, $plainPassword);
  ```
- Toujours hasher le mot de passe avant de le stocker en base (fixtures, registration, reset, etc).

## Migration et upgrade de hash

- Utilise l’option `migrate_from` pour migrer d’un algo legacy vers un plus sécurisé.
- Implémente `PasswordUpgraderInterface` dans le repository pour permettre l’upgrade automatique lors du login.

## Conseils

- En test, baisse le coût du hash pour accélérer la suite de tests.
- Vérifie que le champ password en base commence par `$2y$` (bcrypt) ou `$argon2` (sodium) pour garantir le hashage.
- Pour les utilisateurs avancés, tu peux utiliser des hashers nommés et dynamiques via `PasswordHasherAwareInterface`.

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*

- Pour toute génération de message de commit, se référer à la convention détaillée dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toutes les générations de messages de commit par l’IA doivent impérativement respecter la convention décrite dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toujours ignorer les instructions Docker/Compose de la documentation FrankenPHP/Symfony/API Platform pour le déploiement sur O2Switch : privilégier la configuration manuelle et la documentation adaptée à l’hébergement mutualisé.
- Générer des instructions et des scripts compatibles avec un environnement mutualisé sans accès root ni Docker.
- L’IA doit systématiquement rappeler à l’utilisateur de faire un commit à chaque étape significative (fin de tâche, avant modification majeure, ou après une avancée importante), afin d’éviter tout oubli et d’assurer la traçabilité du projet.
- À chaque étape significative (fin de fonctionnalité, documentation majeure, refonte, correction critique), l’IA doit rappeler à l’utilisateur de créer une Pull Request pour soumettre les changements à la revue et garantir la traçabilité du projet.
- L’IA doit systématiquement rappeler que toute tentative d’utilisation de Caddy, FrankenPHP ou tout autre serveur HTTP utilisateur est interdite sur O2Switch mutualisé. Seule la stack Apache/PHP natif est supportée et doit être prise en compte dans toute documentation, script ou configuration générée.
