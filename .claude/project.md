# HomeCloud — Projet

## Description

API REST + frontend PWA pour héberger et partager des fichiers/médias.

- **Backend** : Symfony 7 / API Platform 3, PHP 8.4, MariaDB, Doctrine ORM, LexikJWT, Symfony Messenger
- **Frontend** : Tailwind CSS v4, Stimulus, PWA
- **Déploiement** : `ronan.lenouvel.me` — auto à chaque push `main` CI ✅ (webhook PHP)

## Entités Doctrine

| Entité   | Rôle                              |
|----------|-----------------------------------|
| `User`   | Compte utilisateur (JWT auth)     |
| `Folder` | Répertoire virtuel                |
| `File`   | Fichier uploadé                   |
| `Media`  | Métadonnées média (image/vidéo)   |

Toutes initialisent leur UUID v7 dans le constructeur — voir [architecture.md](architecture.md).

## Variables d'env minimales (`.env.test.local`)

```
DATABASE_URL=mysql://root:root@127.0.0.1:3306/homecloud?serverVersion=mariadb-10.11.0&charset=utf8mb4
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
APP_SECRET=<valeur dans .env.local>
```

## Secrets & env

- Ne jamais commiter de secrets
- `.env` : valeurs génériques uniquement
- `.env.local` / `.env.test.local` : valeurs sensibles (ignorées par git)
