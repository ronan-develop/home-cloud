# Convention de commit – Home Cloud


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

- [Conventions de PR](CONVENTION_PR.md)

[⬅️ Retour au README](README.md)

## Le commit parfait

Un bon message de commit doit permettre de savoir ce qui a changé et pourquoi. Le comment (la manière d’effectuer ces changements) n’a pas à être expliqué : la lecture du code et le diff suffisent. Il est au format md et peut être opié/collé dans le terminal/git.

### Format adopté (inspiré Angular, avec emoji)

```txt
<emoji> <type>(<scope>): <sujet>

<description>

<footer>
```

- **type** : nature du changement (voir liste ci-dessous)
- **scope** (facultatif) : partie du projet concernée (ex : api, ui, build, tests…)
- **sujet** : description concise (max 50 caractères)
- **description** : explication plus détaillée si besoin (optionnel)
- **footer** : infos complémentaires (issues, breaking change, etc.)

### Types de commit autorisés

- 🧞‍♂️ **ia** : la documentation, instructions qui sont destinées à l’IA, Copilot ou agents IA (**jamais pour les actions humaines classiques, même sur des fichiers d’instructions, de tests ou de configuration**)
- 🛠️ **build** : changements sur le système de build ou dépendances (npm, make…)
- 🤖 **ci** : intégration continue, scripts/config (Travis, Ansible…)
- ✨ **feat** : ajout d’une nouvelle fonctionnalité
- 🐛 **fix** : correction de bug
- 🚀 **perf** : amélioration des performances
- 🧹 **refactor** : refonte sans ajout de fonctionnalité ni perf
- 🎨 **style** : changements de style/code sans impact fonctionnel (indentation, renommage…)
- 📝 **docs** : documentation
- 🧪 **test** : ajout/modif de tests
- ⏪ **revert** : annulation d’un commit précédent
- 🔀 **merge** : fusion de branches (ex : dev → main)

#### Règle stricte emoji IA

> L’emoji 🧞‍♂️ est strictement réservé au type **ia**. Il ne doit jamais être utilisé pour un commit, une PR ou une documentation humaine classique, même sur des fichiers d’instructions, de tests ou de configuration. Pour tout autre sujet, utiliser l’emoji du type de commit approprié ci-dessous.

### Exemples

```txt
✨ feat(api): gestion multi-tenant

Ajoute la logique d’isolation des utilisateurs par sous-domaine.

Closes #42
```

```txt
🐛 fix(cart): correction du calcul de TVA

Corrige un bug sur le calcul de la TVA lors de l’ajout d’un produit au panier.
```

```txt
📝 docs: mise à jour du README

Ajoute la section sur l’installation de Caddy en mutualisé.
```

```txt
⏪ revert: feat(api): gestion multi-tenant 1a2b3c4

Annule le commit d’ajout de la gestion multi-tenant (problème de migration).
```

```txt
🔀 merge(main): fusion de la branche dev dans main

Fusionne les dernières évolutions de la branche dev (docs, procédures d’intégration, TODO, corrections de format) dans la branche principale main.
```

### Conseils

- Toujours commencer par l’emoji correspondant au type.
- Scope facultatif mais recommandé si pertinent.
- Sujet court, à l’infinitif, sans point final.
- Description et footer optionnels, mais utiles pour la clarté.

## Couverture de tests

- La couverture minimale des tests (code coverage) doit être de 80% sur chaque PR ou merge vers main.
- Toute nouvelle fonctionnalité ou correction doit être accompagnée de tests pertinents (unitaires ou d’intégration).

---

*Respecter cette convention pour tous les commits du projet Home Cloud.*
