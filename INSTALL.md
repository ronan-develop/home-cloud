# Installation de Home Cloud

## Installation locale (développement)

### Prérequis Docker

- PHP 8.2 ou supérieur avec les extensions requises par Symfony
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (version recommandée : 18+)
- [pnpm](https://pnpm.io/) (pour la PWA)
- PostgreSQL
- Git

### Étapes Docker

1. **Cloner le dépôt**

   ```bash
   git clone https://github.com/votre-utilisateur/home-cloud.git
   cd home-cloud
   ```

2. **Installer les dépendances backend (API Platform)**

   L'API Platform se trouve dans le dossier `/api` (et non à la racine du projet).

   ```bash
   cd api
   composer install
   cp .env .env.local
   # Adapter les variables d'environnement si besoin (DB, etc.)
   ```

3. **Créer la base de données**

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

4. **Installer les dépendances frontend (PWA)**

   ```bash
   cd ../pwa
   pnpm install
   ```

5. **Lancer les serveurs de développement**

   - **API Platform** :

     ```bash
     cd ../api
     symfony serve -d
     ```

   - **PWA (Next.js)** :

     ```bash
     cd ../pwa
     pnpm dev
     ```

6. **Accéder à l'application**

   - API : <http://localhost:8000>  
   - PWA : <http://localhost:3000>

---

**Remarque** :  
Pour l’utilisation de FrankenPHP ou le déploiement en production, consulte le fichier `archi.md`.

## Installation avec Docker et FrankenPHP

### Prérequis

- Docker et Docker Compose installés
- PHP, Composer, Symfony CLI (pour le développement local)

### Étapes

1. Clonez le dépôt et placez-vous dans le dossier du projet.
2. Vérifiez que le dossier `api/public` contient bien le front controller de l’API Platform (`index.php`).
3. Installez les dépendances PHP dans le conteneur (à refaire à chaque modification du fichier composer.json ou composer.lock) :

   ```sh
   docker compose run --rm php composer install
   ```

   > **Remarque importante** :
   > - Le code source de l’API est monté dans le conteneur sous `/app` (grâce à `volumes: - ./api:/app` dans `compose.override.yaml`).
   > - Il ne faut pas utiliser `--working-dir=api` dans la commande ci-dessus.
   > - Le dossier `vendor` est synchronisé automatiquement entre `./api` (local) et `/app` (conteneur).

4. Démarrez les services avec :

   ```sh
   docker compose up -d --remove-orphans
   ```

5. FrankenPHP sert l’API Platform sur <http://localhost> (ou <https://localhost>).
   - Le dossier `./api/public` est monté dans le conteneur comme racine web :

     ```yaml
     volumes:
       - ./api/public:/app/public:ro
     ```

6. Pour arrêter les services :

   ```sh
   docker compose down
   ```

### Dépannage

- **Erreur `autoload_runtime.php` manquant** : Vérifiez que vous avez bien exécuté `docker compose run --rm php composer install` (sans `--working-dir=api`) et que le dossier `vendor` est bien présent dans `./api`.
- Après toute modification des dépendances PHP, relancez la commande d’installation dans le conteneur.

### Configuration supplémentaire

- Adaptez la variable d’environnement `DATABASE_URL` dans le fichier `.env` pour pointer vers votre base PostgreSQL locale.
- Consultez le fichier `compose.yaml` ou `compose.override.yaml` pour la configuration complète des services.

### Notes

- FrankenPHP remplace Apache comme serveur web principal.
- Pour un usage avancé (certificats SSL, logs, etc.), voir le fichier `archi.md`.
