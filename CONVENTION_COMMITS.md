# Convention de commits

- Utilise des emojis pour le type de commit (voir tableau ci-dessous)
- Structure : `<emoji> type(scope): message court`
- Ajoute une description détaillée si besoin
- Exemples :
  - 🐛 fix(entity): correction d’une typo dans File
  - ✨ feat(api): ajout de l’upload de fichiers
  - 🧹 chore(gitignore): nettoyage des fichiers ignorés

| Emoji | Type    | Description                  |
|-------|---------|------------------------------|
| ✨    | feat    | Nouvelle fonctionnalité       |
| 🐛    | fix     | Correction de bug            |
| 🧹    | chore   | Tâche de maintenance         |
| 📝    | docs    | Documentation                |
| 🚀    | deploy  | Déploiement                  |
| 🔥    | remove  | Suppression de code/fichier  |
| ♻️    | refact  | Refactorisation              |
| ✅    | test    | Ajout/correction de tests    |

## Convention de PR

- Titre explicite, commence par l’emoji du type principal
- Description synthétique + tasklist Markdown
- Lien vers l’issue ou la spec si besoin
- Exemples :
  - ✨ feat: ajout de l’API de partage de fichiers
  - 🐛 fix: correction du bug d’upload

## Tasklist PR

```md
- [ ] Tâche 1
- [ ] Tâche 2
```

## Liens utiles

- [Conventions de commit](CONVENTION_COMMITS.md)
- [Conventions de PR](CONVENTION_PR.md)

[⬅️ Retour au README](README.md)
