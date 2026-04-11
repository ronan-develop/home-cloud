# Git — Conventions et workflow

Référence complète : `.github/CONVENTION_DE_COMMIT.md`

## Format de commit

```
<emoji> <type>(<scope>): <sujet>
```

| Emoji | Type       | Quand                                        |
|-------|------------|----------------------------------------------|
| ✨    | feat       | Nouvelle fonctionnalité                      |
| 🔧    | fix        | Correction de bug                            |
| 📖    | docs       | Documentation uniquement                     |
| ♻️    | refactor   | Refactorisation sans nouvelle feature ni fix |
| ⚡    | perf       | Amélioration des performances                |
| ✅    | test       | Ajout/correction de tests                    |
| 🏗️    | build      | Système de build, dépendances                |
| 🏭    | ci         | Configuration CI, scripts de déploiement     |
| 🛠️    | chore      | Outillage, config, nettoyage                 |
| 🎨    | style      | Formatage, espaces (pas de logique)          |
| 🔒    | security   | Correctifs de sécurité                       |
| ⏪    | revert     | Annulation d'un commit                       |
| 🚧    | WIP        | Travail en cours (éviter sur main)           |

**Règles non négociables :**
- Emoji TOUJOURS présent
- Scope **explicite** : nom de classe/module — pas `feat(file)` ou `feat(api)`
- Commits **atomiques** — jamais `git add .` en un bloc
- Jamais de commit direct sur `main`

## Workflow type

```bash
git checkout main && git pull
git checkout -b feat/NomExplicite

git add src/fichier-concerne.php        # stager par groupe logique
git commit -m "✨ feat(NomClasse): description courte"
# répéter pour chaque groupe logique
```

## Limites autonomie — confirmation obligatoire avant de

- Pusher (`git push`)
- Merger dans `main`
- Ouvrir / fermer une PR
- Supprimer une branche ou des fichiers non triviaux

Le user décide de l'ouverture des PRs et des merges.

## Slash command `/git`

Tape `/git` dans le chat pour déclencher le workflow guidé :
- analyse `git status` + `git diff`
- regroupe les fichiers par responsabilité logique
- propose les messages au bon format
- attend la validation avant chaque `git add` + `git commit`
- vérifie l'absence de secrets dans le diff stagé

Définie dans `.claude/commands/git.md`.
