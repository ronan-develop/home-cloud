````instructions
---
applyTo: '**'
---

# 🧞‍♂️ Instructions IA – Home Cloud

## 0. Règle d’emoji 🧞‍♂️ pour consignes IA

- Toute modification, ajout ou clarification d’une consigne, règle ou documentation destinée à l’IA (Copilot, agent IA, etc.) doit être committée avec l’emoji 🧞‍♂️, même si ce n’est pas généré par Copilot.
- L’emoji 🧞‍♂️ ne doit pas être utilisé pour des commits humains classiques qui ne concernent pas une consigne IA.

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
- L’emoji 🧞‍♂️ doit être utilisé uniquement dans les messages de commit, PR ou documentation qui concernent l’IA, les instructions Copilot, ou la documentation destinée à l’IA.
- Ne jamais utiliser 🧞‍♂️ pour les commits ou actions humaines classiques, même sur des fichiers d’instructions ou de tests.
- Exemples :
  - 🧞‍♂️ docs: mise à jour automatique de la documentation destinée à l’IA
  - 🧞‍♂️ test: refactorisation générée par l’IA

## 8. Bonnes pratiques tests & environnement de test

- Toujours installer le test-pack Symfony pour bénéficier de PHPUnit, BrowserKit, etc. :
  ```bash
  composer require --dev symfony/test-pack
  ```
- Les tests doivent être organisés par type :
  - `tests/Unit/` pour les tests unitaires
  - `tests/Integration/` pour les tests d’intégration
  - `tests/Application/` pour les tests fonctionnels (API, HTTP, E2E)
- Le kernel de test est défini par la variable d’environnement `KERNEL_CLASS` dans `.env.test`.
- La base de test doit être indépendante, suffixée `_test` (ex : `DATABASE_URL=.../ma_base_test`).
- Les fixtures doivent être chargées via le bundle Alice ou DoctrineFixturesBundle, activés pour `dev` et `test` dans `config/bundles.php`.
- Pour garantir l’isolation, utiliser `RefreshDatabaseTrait` ou `DAMA\\DoctrineTestBundle` pour rollback automatique.
- Toujours vérifier que les entités des fixtures sont visibles via un test GET collection avant toute création.
- Pour déboguer, dumper la réponse brute du client dans les tests si la collection est vide ou inattendue.
- Ne jamais utiliser `.env.local` en environnement de test (non pris en compte).
- Les tests doivent être reproductibles, indépendants et ne jamais dépendre de l’état d’un autre test.

### 8.1 Configuration de la base de test et isolation

- Toujours utiliser une base dédiée pour les tests, suffixée `_test` (ex : `DATABASE_URL=.../ma_base_test`).
- Pour un setup partagé, configurez la base dans `.env.test` (commit au dépôt). Pour un setup local, surcharger dans `.env.test.local` (non versionné).
- Création de la base et du schéma :
  ```bash
  php bin/console --env=test doctrine:database:create
  php bin/console --env=test doctrine:schema:create
  ```
- Les tests doivent être indépendants : chaque test doit pouvoir être exécuté seul, sans dépendre de l’état d’un autre.
- Utiliser `DAMA\DoctrineTestBundle` pour rollback automatique des transactions :
  ```bash
  composer require --dev dama/doctrine-test-bundle
  ```
  Puis activer l’extension PHPUnit dans `phpunit.dist.xml` :
  ```xml
  <phpunit>
    <extensions>
      <bootstrap class="DAMA\\DoctrineTestBundle\\PHPUnit\\PHPUnitExtension"/>
    </extensions>
  </phpunit>
  ```
- Charger les fixtures via Alice ou DoctrineFixturesBundle, puis :
  ```bash
  php bin/console --env=test doctrine:fixtures:load
  ```
- Toujours vérifier la visibilité des entités fixtures via un test GET collection avant toute création.

## 9. Exécution des tests

- Lorsque l’utilisateur écrit uniquement le mot « test », l’IA doit automatiquement lancer la commande de tests (ex : `php bin/phpunit`).
- L’IA ne doit proposer de lancer les tests que lorsqu’elle juge cela pertinent (après une modification de code/test, ou sur demande explicite).
- Ne jamais lancer les tests sans raison ou contexte approprié.

## 10. Reporting des tests

- Après chaque exécution de tests, fournir systématiquement un tableau récapitulatif des résultats au format Markdown (succès, échecs, avertissements, etc.).
- Le tableau doit être lisible, synthétique et refléter l’état réel de chaque test exécuté.

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*
````
