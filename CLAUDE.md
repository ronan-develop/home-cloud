# CLAUDE.md — HomeCloud

Sommaire de référence. Lire avant toute intervention — suivre les liens pour le détail.

---

## Index

| Sujet                        | Fichier                                                  |
|------------------------------|----------------------------------------------------------|
| Stack, entités, env vars     | [.claude/project.md](.claude/project.md)                 |
| Architecture `src/`, UUID    | [.claude/architecture.md](.claude/architecture.md)       |
| Commits, branches, `/git`    | [.claude/git-conventions.md](.claude/git-conventions.md) |
| Commandes bin/console, tests | [.claude/commands.md](.claude/commands.md)               |
| Méthodologie TDD             | [.claude/tdd.md](.claude/tdd.md)                         |
| Design frontend              | [.claude/frontend.md](.claude/frontend.md)               |
| CI/CD, déploiement, suivi    | [.claude/cicd.md](.claude/cicd.md)                       |

---

## Règles critiques — toujours actives

### Secrets

- Ne jamais commiter de secrets — relire le diff stagé avant chaque commit
- `.env` : valeurs génériques uniquement ; `.env.local` / `.env.test.local` : valeurs sensibles

### Git

- Jamais de commit direct sur `main`
- Commits atomiques — jamais `git add .` en un bloc
- Confirmation obligatoire avant : `git push`, merge, ouvrir/fermer une PR, supprimer une branche

### TDD

- Toujours RED → GREEN → REFACTOR — ne jamais écrire le code avant le test

### UUID Doctrine

- Initialiser `$this->id = Uuid::v7()` dans le constructeur de chaque entité — voir [.claude/architecture.md](.claude/architecture.md)

---

## Suivi d'avancement

Mettre à jour `.github/avancement.md` après chaque tâche complétée.
