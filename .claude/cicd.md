# CI/CD

## Pipeline

| Job                     | Déclencheur           | Détail                       |
|-------------------------|-----------------------|------------------------------|
| PHPUnit + MariaDB       | push / PR sur `main`  | PHP 8.4, MariaDB 10.11       |
| Jest (JS)               | push / PR sur `main`  | Node.js 22 (LTS)             |
| `composer audit`        | dans le job PHP       | avant les tests              |
| Déploiement webhook     | CI ✅ sur `main`       | déclenché automatiquement    |
| Déploiement manuel      | `workflow_dispatch`   | via GitHub Actions           |

## Déploiement

Webhook PHP sur `ronan.lenouvel.me` — déclenché automatiquement après CI verte sur `main`.

Détail du workflow : `.github/DEPLOY_WORKFLOW.md`  
Secrets nécessaires : `.github/DEPLOY_SECRETS.md`

## Suivi d'avancement

Mettre à jour `.github/avancement.md` après chaque tâche complétée.

| Fichier                          | Contenu                      |
|----------------------------------|------------------------------|
| `.github/avancement.md`          | État actuel, bugs connus     |
| `.github/todo-api-features.md`   | Fonctionnalités API à venir  |
| `.github/todo-user-settings.md`  | Page paramètres utilisateur  |
| `.github/todo-security.md`       | Sécurité restante            |
