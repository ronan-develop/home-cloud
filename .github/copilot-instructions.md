# 📋 Instructions HomeCloud - Starter Pack Commun

**🔴 POINT D'ENTRÉE PRINCIPAL POUR LES AGENTS IA - À LIRE EN PREMIER**

**Référence commune pour tous les projets HomeCloud**

Pour chaque question, tu te réfères à ce fichier et à ses références externes.  
Tu ne réponds jamais "je ne sais pas" — tu bases toujours ta réponse sur la documentation fournie.
Tu ne réponds jamais avec des informations inventées ou non vérifiées.

---

## 📖 Ordre de Lecture Recommandé

**⚠️ À LIRE AVANT CHAQUE RÉPONSE :**
1. **Ce fichier** (tu es ici) → Règles globales du projet
2. **[.github/CONVENTION_DE_COMMIT.md](./.github/CONVENTION_DE_COMMIT.md)** → Convention de commit (emoji OBLIGATOIRE, scope EXPLICITE)
3. **[.github/dev+.chatmode.md](./.github/dev+.chatmode.md)** → Bonnes pratiques de développement

---

## 🔗 Références Essentielles

### 2️⃣ Conventions de Commit — RÈGLES STRICTES
→ Fichier de référence : **[.github/CONVENTION_DE_COMMIT.md](./.github/CONVENTION_DE_COMMIT.md)**

**Format OBLIGATOIRE :** `<emoji> <type>(<scope>): <sujet>`

**Règles non négociables :**
- L'**emoji** est TOUJOURS présent en début de message (ex: `✨`, `🔧`, `✅`, `🏗️`)
- Le **scope** doit être **explicite et concret** : nom de la classe, du module ou du composant concerné
  - ✅ `feat(FileUploadController)`, `fix(UserTest)`, `test(FileTest)`
  - ❌ `feat(file)`, `feat(api)`, `fix(tests)` ← trop vague
- Les commits sont **atomiques** : un commit = une responsabilité logique
- Pour `#git` : créer autant de commits que nécessaire, jamais `git add .` en un seul bloc

**Correspondance emoji ↔ type :**
| Emoji | Type |
|-------|------|
| ✨ | feat |
| 🔧 | fix |
| 📖 | docs |
| ♻️ | refactor |
| ⚡ | perf |
| ✅ | test |
| 🏗️ | build |
| 🏭 | ci |
| 🛠️ | chore |
| 🎨 | style |
| 🔒 | security |
| ⏪ | revert |
| 🚧 | WIP |

### 3️⃣ Git Workflow
**RÈGLE ABSOLUE : ne jamais commiter directement sur `main`.**
Toujours créer une branche avant de travailler.

Quand je tape la commande `#git` dans le chat ou la CLI, suivre ce workflow :
```bash
# Si pas encore sur une branche de travail, en créer une
git checkout -b feat/NomExplicite   # ou fix/, refactor/, chore/...

git diff                    # Identifier les changements et regrouper logiquement
git status                  # Vérifier l'état
# Stager et commiter par groupe logique (commits atomiques)
git add <fichiers-liés>
git commit -m "✨ feat(NomExplicite): description courte"
# Répéter pour chaque groupe logique
```
**Le user ne push PAS — commits locaux uniquement.**
**Le merge dans main est décidé par le user, pas par l'agent.**

---

## 📋 Mémoire & Suivi des Travaux

Un fichier d'avancement des travaux est présent dans [`.github/avancement.md`](./.github/avancement.md). Ce fichier doit être mis à jour régulièrement pour refléter l'état actuel des travaux. Tu peux effectuer seul ces mises à jour.

---

## 🧪 Méthodologie TDD — OBLIGATOIRE

**Pour toute nouvelle fonctionnalité ou entité, la règle est :**

1. **RED** — Écrire le test d'abord (il doit échouer)
2. **GREEN** — Écrire le minimum de code pour le faire passer
3. **REFACTOR** — Nettoyer sans casser les tests

**Règles strictes :**
- Ne jamais écrire du code de production sans test préalable
- Un commit RED (test seul) avant le commit GREEN (implémentation)
- Les tests fonctionnels API couvrent : status HTTP, structure JSON, cas d'erreur (404, 400...)
- Stack : PHPUnit + `symfony/test-pack` + `ApiTestCase` (API Platform)
