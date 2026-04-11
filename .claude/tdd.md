# Méthodologie TDD — OBLIGATOIRE

## Cycle

1. **RED** — écrire le test (il échoue)
2. **GREEN** — implémenter le minimum pour le faire passer
3. **REFACTOR** — nettoyer sans casser les tests

Ne jamais passer à GREEN sans avoir vu le test échouer en RED.

## Stack

- **PHPUnit** + `symfony/test-pack`
- **ApiTestCase** (API Platform) pour les tests d'endpoints
- **Jest** pour le JS

## Couverture attendue par feature

- Accès non autorisé (ownership check)
- Cas nominal (happy path)
- Cas limites (validation, conflit de noms, données manquantes)
- Rollback / erreur (ex: échec de stockage physique)
