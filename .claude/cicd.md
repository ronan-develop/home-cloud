# CI/CD

## Pipeline

| Job                     | Déclencheur           | Détail                       |
|-------------------------|-----------------------|------------------------------|
| PHPUnit + MariaDB       | push / PR sur `main`  | PHP 8.4, MariaDB 10.11       |
| Jest (JS)               | push / PR sur `main`  | Node.js 22 (LTS)             |
| `composer audit`        | dans le job PHP       | avant les tests              |

## Déploiement

**Nocturne et autonome depuis #421 (2026-09-12)** — un merge sur `main` (CI verte) déclenche, via un cron cPanel côté serveur, un déploiement automatique la nuit suivante (1h-2h30, heure de Paris). Le serveur **tire** depuis GitHub (`git fetch` + comparaison de SHA) ; GitHub ne pousse jamais vers les instances.

`ronan.lenouvel.me` (instance personnelle, aucun autre utilisateur) se déploie immédiatement via `bash bin/deploy-all.sh` sans flag ; les 6 autres instances sont automatiquement différées à la nuit. `--now` force un déploiement immédiat des 7 en cas d'urgence, avec confirmation interactive obligatoire.

**o2switch bloque bien le SSH** pour les runners GitHub Actions (whitelist cPanel) — mais un test empirique (2026-09-12) a aussi révélé qu'un **WAF applicatif** coupe la connexion HTTPS d'un runner GitHub spécifiquement sur `public/deploy.php`, alors que le reste du domaine répond normalement à la même IP. Le webhook GitHub → instances a donc été écarté au profit du sens inverse (serveur → GitHub), qui ne dépend d'aucune connexion entrante. `public/deploy.php` reste dans le repo, désarmé (secret retiré), en attendant sa suppression après validation du nouveau flux.

Le ticket #288 (whitelist SSH dynamique pour un déclenchement GitHub → serveur) est donc **fermé** : la question qu'il posait est tranchée par une architecture différente, pas résolue dans le sens qu'il envisageait.

Détail complet, crons, diagnostic : `.claude/deploiement.md`.

## Suivi d'avancement

Mettre à jour `.github/avancement.md` après chaque tâche complétée.

| Fichier                          | Contenu                      |
|----------------------------------|------------------------------|
| `.github/avancement.md`          | État actuel, bugs connus     |
| `.github/todo-api-features.md`   | Fonctionnalités API à venir  |
| `.github/todo-user-settings.md`  | Page paramètres utilisateur  |
| `.github/todo-security.md`       | Sécurité restante            |
