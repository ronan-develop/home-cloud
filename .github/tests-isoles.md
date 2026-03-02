---
applyTo: '**'
---

# 📋 Tests isolés — Explications détaillées

## 1. Liste des tests isolés

**Fichier concerné :** [tests/Api/FileTest.php](tests/Api/FileTest.php)

- `testCreateFileWithSameNameInSameFolder` (skipped)
- `testCreateFileWithSameNameInDifferentFolder` (skipped)
- 1 test incomplet (probablement lié aux deux précédents)

---

## 2. Pourquoi ces tests sont isolés ?

### a) testCreateFileWithSameNameInSameFolder
- **Objectif :** Vérifier que l’API refuse la création de deux fichiers avec le même nom dans le même dossier (unicité).
- **Problème :** Exception `UniqueConstraintViolationException` sur le champ UUID, collision d’identifiants ou persistance non isolée.
- **Cause racine :**
  - UUID généré dans le constructeur, mais le contexte Doctrine (EntityManager) n’est pas réinitialisé entre les tests.
  - Collision d’UUID ou entités non nettoyées.
  - Les helpers de test ne garantissent pas l’isolation transactionnelle.
- **Conséquence :** Test ignoré pour éviter de fausser la suite globale.

### b) testCreateFileWithSameNameInDifferentFolder
- **Objectif :** Vérifier que l’API autorise la création de fichiers avec le même nom dans des dossiers différents.
- **Problème :** Même symptôme : collision d’UUID ou entités non isolées.
- **Cause racine :** Identique au test précédent.
- **Conséquence :** Test ignoré pour éviter de fausser la suite globale.

### c) Test incomplet
- **Cause probable :** Utilisation de `$this->markTestIncomplete()` ou exception non gérée.
- **Fichier :** [tests/Api/FileTest.php](tests/Api/FileTest.php)

---

## 3. Explication technique détaillée

- **Doctrine & UUID v7 :** Les entités génèrent leur UUID dans le constructeur. Si le contexte Doctrine n’est pas réinitialisé ou si les entités ne sont pas détachées/supprimées entre les tests, des collisions ou violations de contraintes surviennent.
- **Isolation des tests :**
  - Utiliser un EntityManager propre ou réinitialisé.
  - Nettoyer la base (truncate, rollback, etc.) entre les tests.
  - S’assurer que les UUID générés sont uniques et non réutilisés.
- **Effet sur la suite globale :** Les tests isolés sont ignorés pour permettre la validation des autres fonctionnalités.

---

## 4. Comment corriger ?

- Refactoriser les helpers de test pour garantir une isolation transactionnelle stricte.
- Utiliser des fixtures ou des transactions rollback après chaque test.
- S’assurer que chaque test génère des entités avec des UUID uniques et ne réutilise pas le même contexte Doctrine.
- Réactiver les tests une fois l’isolation garantie.

---

## 5. Récapitulatif

- **Tests isolés :** 2 tests dans [tests/Api/FileTest.php](tests/Api/FileTest.php) (skipped), 1 incomplet.
- **Raison :** Collision d’UUID, mauvaise isolation du contexte Doctrine, persistance non nettoyée.
- **Solution :** Refactorisation des helpers et du cycle de vie des entités en test.

---

## 6. Liste des tâches

```
- [x] Identifier les tests isolés (FileTest.php)
- [x] Expliquer la cause technique de l’isolement
- [x] Détail sur Doctrine, UUID et isolation
- [x] Proposer les pistes de correction
```

---

> Pour réactiver ces tests, il faut garantir l’isolation transactionnelle et la génération d’UUID uniques à chaque run.
