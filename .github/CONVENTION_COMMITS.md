# Convention de commit – Home Cloud

## Le commit parfait

Un bon message de commit doit permettre de savoir ce qui a changé et pourquoi. Le comment (la manière d’effectuer ces changements) n’a pas à être expliqué : la lecture du code et le diff suffisent.

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

---

*Respecter cette convention pour tous les commits du projet Home Cloud.*
