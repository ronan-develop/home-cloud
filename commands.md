# Commands

## Lancer le serveur Symfony en HTTPS (développement)

1. Installer le certificat local (une seule fois) :

   ```bash
   symfony server:ca:install
   ```

2. Lancer le serveur en HTTPS :

   ```bash
   symfony serve -d --port=8000 --https
   ```

