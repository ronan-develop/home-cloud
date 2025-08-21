---
applyTo: '**'
---

# 🧞‍♂️ Instructions IA – Home Cloud

## 1. Contexte et architecture
- Projet Symfony 7/API Platform multi-tenant (hébergement O2Switch, Apache/PHP natif, pas de Docker/root)
- Chaque sous-domaine = espace privé, base dédiée (hc_<username>)
- Documentation centrale : `.github/projet-context.md`
- Synchroniser ce fichier à chaque évolution majeure

## 2. Sécurité & bonnes pratiques
- Jamais de credentials dans le dépôt
- Scripts/docs toujours compatibles mutualisé (pas de Caddy/FrankenPHP/Docker)
- Privilégier la traçabilité, la documentation métier et technique

## 3. API & Modélisation
- API REST (API Platform), modélisation orientée particuliers
- Partage natif de fichiers/dossiers, gestion des droits, logs, expiration
- Documentation à jour : README, classes.puml, api_endpoints.md

## 4. Workflow tests & commits
- Workflow snapshot :
  1. Créer branche snapshot (`test/snapshot-...`)
  2. Commit état initial
  3. PR snapshot vers branche d’origine
  4. Refonte sur nouvelle branche
  5. PR refonte liée à la PR snapshot
- Toujours commit/PR à chaque étape significative
- Générer les messages de commit/PR selon `.github/CONVENTION_COMMITS.md` (format, labels, #tags, emoji 🧞‍♂️)
- Fournir systématiquement la tasklist et le tableau de résultats de tests au format Markdown

## 5. Tasklists & reporting
- Toujours utiliser le format Markdown strict pour les listes de tâches :
```
- [ ] Tâche 1
- [ ] Tâche 2
```
- Toujours entourer la liste de tâches de triples backticks
- Pour les tests, fournir un tableau récapitulatif Markdown

## 6. Convention de labelisation snapshot
- Label : `snapshot`
- Couleur : `#6f42c1` (violet)
- Description : Snapshot d’état du code ou des données avant refonte ou évolution majeure. Permet de tracer, archiver et faciliter le rollback.

## 7. Convention d’emoji IA
- Toute action, commit, PR ou doc générée par l’IA commence par 🧞‍♂️
- Exemples :
  - 🧞‍♂️ docs: mise à jour automatique de la documentation
  - 🧞‍♂️ test: refactorisation générée par l’IA

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*
