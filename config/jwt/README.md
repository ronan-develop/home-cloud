README - clés JWT

Ce dossier contient les clés utilisées pour l'authentification JWT (LexikJWTAuthenticationBundle).

Génération (recommandé via le script à la racine du projet):

```bash
bash scripts/generate_jwt_keys.sh
```

Fichiers créés:
- `private.pem` : clé privée (NE PAS COMMIT)
- `public.pem`  : clé publique

Sécurité:
- Protéger `private.pem` avec des permissions strictes (600).
- Utiliser un store sécurisé pour les environnements de production (Vault, variables d'env chiffrées, etc.).

Configuration:
- Ajouter la config Lexik dans `config/packages/lexik_jwt_authentication.yaml`.
- Exemple de config se trouve dans la documentation du bundle.
