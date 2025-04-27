# Installation locale de Home Cloud

## Prérequis

- PHP 8.2 ou supérieur avec les extensions requises par Symfony
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (version recommandée : 18+)
- [pnpm](https://pnpm.io/) (pour la PWA)
- PostgreSQL
- Git

## Étapes d'installation

1. **Cloner le dépôt**

   ```bash
   git clone https://github.com/votre-utilisateur/home-cloud.git
   cd home-cloud
   ```

2. **Installer les dépendances backend (API Symfony)**

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

   - **API Symfony** :

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
