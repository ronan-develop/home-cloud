# 📋 Instructions HomeCloud - Starter Pack Commun

---
## 🚦 Consignes de workflow à respecter (ajoutées le 2026-03-02)

**À CONSULTER AVANT CHAQUE INTERVENTION**

- Toujours créer une branche dédiée avant toute modification (jamais de commit direct sur main)
- Commits atomiques : un commit = une responsabilité logique, message explicite (voir conventions plus bas)
- Approche TDD obligatoire :
  1. Écrire le test RED (qui échoue)
  2. Implémenter le code pour le faire passer (GREEN)
  3. Refactoriser si besoin (REFACTOR)
- Mettre à jour régulièrement `.github/avancement.md` pour tracer l’état d’avancement réel (étape, phase, tests, etc.)

---

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

---

## 🎨 Design Frontend — Directives

**Style visuel :** Material Design + Liquid Glass — simple, épuré, efficace.

### 📚 Références

| Source | URL | Ce qu'on y trouve |
|--------|-----|-------------------|
| FreeFrontend Liquid Glass | https://freefrontend.com/css-liquid-glass/ | Catalogue de démos, descriptions techniques |
| Apple Liquid Glass UI (CSS pur) | https://codepen.io/adamcurzon/pen/NPqwOby | Implémentation CSS reference (glassmorphism + mouse-tracking) |
| CSS Liquid Glass (alexerlandsson) | https://codepen.io/alexerlandsson/pen/GgJQEKE | `feDisplacementMap` + `feSpecularLighting` — recréation fidèle Apple |

### Qu'est-ce que le Liquid Glass (vs glassmorphism simple) ?

| Technique | Glassmorphism classique | Liquid Glass |
|-----------|------------------------|--------------|
| Fond flou | `backdrop-filter: blur()` | `backdrop-filter: blur() saturate() brightness()` |
| Distorsion | ❌ | SVG `feTurbulence` + `feDisplacementMap` |
| Reflets | ❌ | `feSpecularLighting` ou gradient CSS multicouches |
| Bord lumineux | `border: 1px solid rgba(white, 0.2)` | Ligne interne `via-white/60` + border subtile |
| Fond requis | Quelconque | **Obligatoire** : fond coloré/dynamique visible derrière |

### Architecture d'un composant Liquid Glass (HTML/CSS pur)

```html
<!-- Fond animé (blobs de couleur) — OBLIGATOIRE pour que l'effet soit visible -->
<div class="animated-bg">...</div>

<!-- SVG filter caché -->
<svg class="hidden">
  <defs>
    <filter id="lg">
      <feTurbulence type="fractalNoise" baseFrequency="0.55 0.65" numOctaves="2" seed="5"/>
      <feDisplacementMap in="SourceGraphic" scale="6" xChannelSelector="R" yChannelSelector="G"/>
    </filter>
  </defs>
</svg>

<!-- Carte Liquid Glass (4 couches) -->
<div class="lg-card"> <!-- position: relative; overflow: hidden; border-radius -->
  <!-- Couche 1 : distorsion + flou -->
  <div class="lg-blur"/> <!-- backdrop-filter: blur(24px) saturate(200%); filter: url(#lg) -->
  <!-- Couche 2 : teinte -->
  <div class="lg-tint"/> <!-- bg-white/10 ou bg-white/15 -->
  <!-- Couche 3 : reflet spéculaire (gradient diagonale claire → sombre) -->
  <div class="lg-shine"/> <!-- background: linear-gradient(135deg, rgba(255,255,255,0.35), rgba(255,255,255,0.05)) -->
  <!-- Couche 4 : ligne de reflet supérieure -->
  <div class="lg-topline"/> <!-- gradient horizontal blanc translucide -->
  <!-- Couche 5 : contenu -->
  <div class="lg-content relative z-10">...</div>
</div>
```

### Principes Material Design
- Surfaces élevées, ombres douces, typographie claire
- États interactifs explicites (hover, focus, active)
- Hiérarchie visuelle via taille/poids de police, pas via couleurs

### Palette (Tailwind CSS v4)
| Rôle | Valeur |
|------|--------|
| Fond page (dark) | `from-slate-900 via-blue-950 to-indigo-900` |
| Surface Liquid Glass | `bg-white/10 backdrop-blur-2xl` + SVG filter |
| Reflet spéculaire | `linear-gradient(135deg, rgba(255,255,255,0.35) 0%, rgba(255,255,255,0.05) 100%)` |
| Accent primaire | `bg-blue-500` / `text-blue-400` (sur fond sombre) |
| Texte principal | `text-white` (fond sombre) ou `text-gray-900` (fond clair) |
| Texte secondaire | `text-white/60` ou `text-gray-400` |

### Règles strictes
- Toujours `rounded-2xl` ou `rounded-3xl` sur les cartes
- `transition-all` sur tous les éléments interactifs
- `focus:outline-none focus:ring-2 focus:ring-blue-400/50` sur inputs/boutons
- Le fond **doit** être coloré et dynamique pour que Liquid Glass soit visible
- Ne jamais mettre du texte directement sur le fond flou sans couche de contenu dédiée
