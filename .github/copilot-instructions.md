# 📋 Instructions HomeCloud - Starter Pack Commun

**🔴 POINT D'ENTRÉE PRINCIPAL POUR LES AGENTS IA - À LIRE EN PREMIER**

**Référence commune pour tous les projets HomeCloud**

Pour chaque question, tu te réfères à ce fichier et à ses références externes.  
Tu ne réponds jamais "je ne sais pas" — tu bases toujours ta réponse sur la documentation fournie.
Tu ne réponds jamais avec des informations inventées ou non vérifiées.

---

## 📖 Ordre de Lecture Recommandé

1. **Ce fichier** (tu es ici) → Context métier Orange + Références essentielles
2. **[.github/dev+.chatmode.md](./.github/dev+.chatmode.md)** → Bonnes pratiques de développement + utilisation optimale du chat mode

---

## 🔗 Références Essentielles

### 2️⃣ Conventions & Commit
→ **[docs/CONVENTION_DE_COMMIT.md](../docs/CONVENTION_DE_COMMIT.md)**
- Format: `<type>(<scope>): <sujet>`
- Types: ✨ feat, 🔧 fix, 📖 docs, ♻️ refactor, ⚡ perf, etc.

### 3️⃣ Git Workflow
Quand je tape la comande `#git` dans le chat ou la CLI, tu me réponds avec les étapes suivantes pour le workflow de commit local :
Toujours respecter [les conventions de commit] (../docs/CONVENTION_DE_COMMIT.md) et suivre ce workflow pour les commits locaux (sans push) :
```bash
git diff                             # Vérifier les changements non stagés et regrouper logiquement les changements
git status                           # Check changes
git add .                            # Stage all
git commit -m "✨ feat(PSC): desc"   # Commit with convention (user does NOT push)
```
---

## 📋 Mémoire & Suivi des Travaux

Un fichier d'avancement des travaux est présent dans [`.github/avancement.md`](./.github/avancement.md). Ce fichier doit être mis à jour régulièrement pour refléter l'état actuel des travaux. Tu peux effectuer seul ces mises à jour.
