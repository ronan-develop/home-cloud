# CI/CD

## Pipeline

| Job                     | Déclencheur           | Détail                       |
|-------------------------|-----------------------|------------------------------|
| PHPUnit + MariaDB       | push / PR sur `main`  | PHP 8.4, MariaDB 10.11       |
| Jest (JS)               | push / PR sur `main`  | Node.js 22 (LTS)             |
| `composer audit`        | dans le job PHP       | avant les tests              |

## Déploiement

**Manuel, pas automatique** — `bash bin/deploy-all.sh` après merge sur `main` (CI verte requise avant de lancer). o2switch bloque les IPs des runners GitHub Actions (whitelist SSH obligatoire), donc aucun déclenchement automatique n'est possible depuis GitHub aujourd'hui.

Un webhook PHP (`public/deploy.php`) existe dans le repo mais est **cassé et inutilisé** (signature HMAC invalide depuis plusieurs jours au 2026-07-20) — ne pas s'y fier, ni supposer qu'un push sur `main` déploie quoi que ce soit automatiquement.

Piste d'automatisation via GitHub Actions + l'API `SshWhitelist` d'o2switch (whitelist dynamique de l'IP du runner) : voir #288 (en pause, décision actée de rester en manuel pour l'instant).

Détail complet : `.claude/deploiement.md`.

## Suivi d'avancement

Mettre à jour `.github/avancement.md` après chaque tâche complétée.

| Fichier                          | Contenu                      |
|----------------------------------|------------------------------|
| `.github/avancement.md`          | État actuel, bugs connus     |
| `.github/todo-api-features.md`   | Fonctionnalités API à venir  |
| `.github/todo-user-settings.md`  | Page paramètres utilisateur  |
| `.github/todo-security.md`       | Sécurité restante            |
