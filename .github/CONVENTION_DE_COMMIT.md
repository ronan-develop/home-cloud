# Convention de Commit

Utilisez le format suivant pour vos messages de commit :

```txt
<type>(<scope>): <sujet>
```

- **type** : feat, fix, docs, style, refactor, test, chore, etc.
- **scope** : (optionnel) la partie du projet concernée
- **sujet** : description courte du changement

`type` peut être l’un des suivants :

- 🏗️ **build** : changements qui affectent le système de build ou les dépendances externes
- 🏭 **ci** : changements concernant les fichiers de configuration et les scripts CI
- 🛠️ **chore** : travail sur l'outillage autre que le build
- 📖 **docs** : documentation uniquement
- ✨ **feat** : nouvelle fonctionnalité
- 🔧 **fix** : correction de bug
- ⚡ **perf** : amélioration des performances
- ♻️ **refactor** : refactorisation du code sans ajout de fonctionnalité ni correction de bug
- 🎨 **style** : changements qui n'affectent pas le sens du code (formatage, espaces, etc.)
- ✅ **test** : ajout de tests manquants ou correction de tests existants
- ⏪ **revert** : annulation d’un commit précédent
- 🚧 **WIP** : travail en cours (Work In Progress)
- 🔒 **security** : changements liés à la sécurité
- 🔖 **release** : version de publication
