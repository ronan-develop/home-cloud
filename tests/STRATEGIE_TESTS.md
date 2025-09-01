# Stratégie de test Home Cloud

## Objectifs

- Garantir la robustesse, la reproductibilité et l’isolation stricte de tous les tests (unitaires, intégration, API Platform)
- S’assurer que chaque test part d’un état de base vierge, sans pollution d’état ni dépendance à l’ordre d’exécution
- Couvrir tous les cas d’usage critiques (relations OneToOne, droits d’accès, isolation multi-tenant)

## Principes clés

- **Isolation stricte** : chaque test fonctionnel API réinitialise la base (drop/create schema + fixtures) avant exécution
- **Fixtures cohérentes** : chaque User n’a qu’un seul PrivateSpace associé (OneToOne), pas de duplication
- **Tests CRUD API** : chaque test crée dynamiquement un nouvel utilisateur avant de créer un PrivateSpace
- **Tests d’intégration** : validés sur une base dédiée, avec migration appliquée et fixtures chargées
- **Pas d’isolation transactionnelle** : non compatible avec API Platform (kernel HTTP)
- **Reporting** : chaque exécution de tests génère un tableau de synthèse Markdown (succès, échecs, avertissements)

## Organisation des tests

- `tests/Unit/` : tests unitaires purs (services, helpers, etc.)
- `tests/Integration/` : tests d’intégration (relations ORM, persistance, fixtures)
- `tests/Application/` : tests fonctionnels API Platform (endpoints, HTTP, E2E)
- `tests/Api/` : tests API Platform CRUD, respectant l’isolation stricte

## Pattern d’isolation API Platform

```php
protected function setUp(): void
{
    parent::setUp();
    shell_exec('php bin/console --env=test doctrine:schema:drop --force');
    shell_exec('php bin/console --env=test doctrine:schema:create');
    shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
}
```

- Ce pattern est appliqué dans chaque classe de test API Platform dépendant des données.
- Il garantit que chaque test part d’une base vierge, sans pollution d’état.

## Références

- [.github/copilot-instructions.md](.github/copilot-instructions.md) : conventions IA et bonnes pratiques tests
- [TESTS_HISTORIQUE.md](../TESTS_HISTORIQUE.md) : historique détaillé des campagnes de tests

## Pour aller plus loin

- Voir la documentation officielle Symfony et API Platform sur les tests fonctionnels et l’isolation des données.
