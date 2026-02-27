# Convention de Documentation - Orange USC Partenaires

## Principes Généraux

- **Clarté:** Langage clair, éviter le jargon technique inutile
- **Complétude:** Documenter l'intention (pourquoi), la structure (quoi), et l'utilisation (comment)
- **Traçabilité:** Référencer les tickets, versions, dates de modification
- **Maintenabilité:** Faciliter les futures modifications et refactorisations

---

## 1. Structure de Document

### 1.1 En-tête Standard

```markdown
---
title: "[COMPOSANT] - [Description courte]"
category: "PSC|HubData|MRC|Infrastructure"
version: "1.0"
author: "[Prénom Nom]"
date: "aaaa-mm-jj"
status: "Brouillon|En révision|Validé|Archivé"
---

# [Titre Principal]

**Dernière modification:** aaaa-mm-jj  
**Responsable:** [Service/Équipe]  
**Révision requise:** [date si applicable]
```

### 1.2 Sections Standard

Chaque document technique doit contenir (si applicable):

```txt
1. Contexte & Objectifs
2. Architecture & Design
3. Flux de Données
4. Règles Métier Critiques
5. Guide d'Utilisation
6. Troubleshooting
7. Références & Dépendances
8. Historique des Modifications
```

---

## 2. Conventions de Style

### 2.1 Titres

```markdown
# Titre Principal (H1) - Un seul par document
## Section Majeure (H2)
### Sous-section (H3)
#### Détail (H4) - Limiter à ce niveau
```

### 2.2 Listes

**Listes à puces:** Utilisez `-` ou `·` pour les listes non ordonnées

```markdown
- Élément principal
  - Sous-élément
    - Détail
- Autre élément
```

**Listes numérotées:** Uniquement pour séquences ou priorités

```markdown
1. Étape 1 - Description
2. Étape 2 - Description
   - Détail optionnel
   - Autre détail
```

### 2.3 Mise en Évidence

```markdown
**Texte en gras** = Concepts clés, termes métier
*Texte en italique* = Nuances, contexte additionnel
`code inline` = Noms de tables, colonnes, fonctions SQL
[Lien](url) = Références croisées
```

### 2.4 Tableaux

Format Markdown standard:

```markdown
| Colonne 1 | Colonne 2 | État |
|-----------|-----------|------|
| Valeur A  | Valeur B  | ✅   |
| Valeur C  | Valeur D  | ❌   |
```

**Conventions d'état:**

- ✅ = OK / Supporté / Valide
- ⚠️ = Attention requise / À améliorer
- ❌ = Non supporté / Erreur / Blocant
- 🔄 = En cours / À documenter
- 📋 = À faire / Pending

---

## 3. Documentation SQL Server

### 3.1 En-tête de Procédure Stockée

```sql
/****** Object:  StoredProcedure [dbo].[NOM_SP]    Description ******/
/**
 * @description: Courte description de la procédure
 * @author: Prénom Nom
 * @created: aaaa-mm-jj
 * @modified: aaaa-mm-jj (Brève modification)
 * @version: 1.0
 * @dependencies: Fn_Fonction, Table1, Table2
 * @usage: EXEC dbo.NOM_SP @param1='valeur'
 */
```

### 3.2 Paramètres Documentés

```sql
DECLARE @parametre1 VARCHAR(100)  -- Description courte
DECLARE @startDate DATETIME      -- Format: YYYY-MM-DD HH:mm:ss
DECLARE @isActive BIT            -- 0=Inactif, 1=Actif
```

### 3.3 Commentaires Code

- **Sections importantes:** `-- ====== Description ======`
- **Logique complexe:** Commenter le POURQUOI, pas le QUOI
- **TODO/Futur:** `-- TODO: [Description]` avec date si possible

```sql
-- ====== Récupération des affectations valides ======
-- Important: Ne récupérer que les affectations où STARTTIME <= @EndDate
-- et ENDTIME est NULL ou > @StartDate (cas limite: migration 02/03)
SELECT ...
```

---

## 4. Diagrammes & Visualisations

### 4.1 Format PlantUML

Tous les diagrammes architecturaux doivent être en `.puml`:

```markdown
## Flux de Données

![Diagram Label](path/to/diagram.puml)

**Légende:**
- Rectangle = Table/Entité
- Diamond = Décision/Condition
- Circle = Début/Fin
```

### 4.2 Diagrammes Requis

- **Architecture générale:** Vue d'ensemble du système
- **Flux critiques:** Migrations, basculements (ex: 02/03)
- **Dépendances:** Quelles tables → quelles sorties

---

## 5. Exemples & Cas d'Usage

### 5.1 Format Standard

```markdown
### Exemple: [Cas d'Usage]

**Scénario:** Courte description du contexte

**Données d'entrée:**
- Table A: [colonnes clés]
- Paramètre @StartDate: '2026-03-02'

**Résultat attendu:**
- X lignes remontées
- Colonnes: [liste]

**Query/Vérification:**
\`\`\`sql
SELECT ... FROM TableA WHERE condition
\`\`\`

**Interprétation:** ✅ Comportement correct / ⚠️ À investiguer
```

---

## 6. Tableau de Couverture

Pour les analyses critiques (ex: audit), utiliser ce format:

```markdown
| Composant | Critère | État | Impact | Action |
|-----------|---------|------|--------|--------|
| MRC       | Migration 02/03 | ✅ OK | Aucun | Accepté |
| HubData   | INNER JOIN orphelins | ⚠️ Design | Perte silencieuse | Monitorer |
| PSC       | Future-proof | ✅ TVF dynamique | Scalable | Accepté |
```

---

## 7. Références Croisées

### 7.1 Lier les Documents

```markdown
- **Voir aussi:** [Architecture HubData](docs/HUBDATA/OPOCI_HubData_organisation.md)
- **Dépendance:** Nécessite Fn_ArbreOrganisation (voir [TVF Documentation](docs/FONCTIONS/Fn_ArbreOrganisation.md))
- **Exemple d'utilisation:** [Regain_OPOCI_PSC_agent.sql](PSC/Regain_OPOCI_PSC_agent.sql#L42)
```

### 7.2 Versionning de Références

```markdown
- **Version:** 2019 SQL Server (ou plus récent)
- **Dépendance externe:** Verint 15.2.1042.84
- **Branche Git:** PSC (version testée)
```

---

## 8. Historique des Modifications

À la fin de chaque document technique:

```markdown
## Historique

| Date | Auteur | Version | Modification | État |
|------|--------|---------|----------------|------|
| 2026-02-20 | Opoci | 1.0 | Création document audit 02/03 | ✅ Validé |
| 2026-02-19 | Opoci | 0.9 | Brouillon initial | 🔄 Révision |
```

---

## 9. Documentation de Bloquants

Format standart pour documenters les problèmes critiques:

```markdown
## 🚨 Bloquant Critique

**ID:** [JIRA/Ticket reference si applicable]  
**Sévérité:** Critique / Haute / Moyenne / Basse  
**Composant:** [Composant affecté]  
**Découverte:** [Date]  
**État:** 🔴 Ouvert / 🟡 En cours / 🟢 Fermé

### Description

Courte description du problème.

### Scénario de reproduction

Étapes exactes pour reproduire.

### Impact

- [Impact 1]
- [Impact 2]

### Action requise

- [ ] Tâche 1
- [ ] Tâche 2 (priorité)
- [ ] Tâche 3 (post-gel)

### Résolution

Description de la correction une fois appliquée.
```

---

## 10. Checklist pour Validation

Avant de pusher une doc:

- [ ] Titre clair et descriptif
- [ ] En-tête avec métadonnées (date, auteur, version)
- [ ] Sections pertinentes complétées (minimum: Objectif, Architecture, Utilisation)
- [ ] Diagrammes ou tableaux si complexité > moyenne
- [ ] Exemples avec résultats attendus
- [ ] Références croisées vers docs connexes
- [ ] Pas de typos / langage clair
- [ ] Historique de modification à jour
- [ ] Lien dans README.md si doc majeure

---

## 11. Emplacement des Fichiers

```
docs/
├── README.md                         # Vue d'ensemble
├── CONVENTION_DOCUMENTATION.md       # Ce fichier
├── CONVENTION_DE_COMMIT.md
├── {COMPOSANT}/
│   ├── README_{COMPOSANT}.md         # Vue d'ensemble du composant
│   ├── ARCHITECTURE.md               # Design technique
│   ├── FLUX.md                       # Flux de données
│   ├── TROUBLESHOOTING.md            # FAQ et debug
│   └── {subfiles}.md
├── HUBDATA/
│   ├── OPOCI_HubData_organisation.md
│   ├── OPOCI_HubData_agent.md
│   └── ...
└── MRC/
    └── ...
```

---

## Exemples Actifs dans le Projet

- ✅ [`docs/README_OPOCI_PSC_UnionBuilder_activite.md`](docs/README_OPOCI_PSC_UnionBuilder_activite.md) - Bonne structure
- ✅ [`docs/HUBDATA/OPOCI_HubData_agent.md`](docs/HUBDATA/OPOCI_HubData_agent.md) - Tableaux de couverture
- ✅ [`.github/AUDIT_COMPLET_02_03_GEL_25_02.md`](.github/AUDIT_COMPLET_02_03_GEL_25_02.md) - Format audit

---

## Questions / Révisions

Pour questions ou améliorations à cette convention, consulter le responsable technique.

**Dernière révision:** 2026-02-20  
**Prochaine révision:**  [À définir par MOA]
