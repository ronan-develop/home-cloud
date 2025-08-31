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

## 7. Convention d’emoji pour les commits

- Tous les messages de commit doivent obligatoirement comporter un emoji en début de message, choisi selon le sujet du commit (voir `.github/CONVENTION_COMMITS.md`).
- L’emoji 🧞‍♂️ est strictement réservé aux commits, PR ou documentation qui concernent l’IA, les instructions Copilot, ou la documentation destinée à l’IA.
- Pour tout autre sujet (code, doc métier, tests, refactor, etc.), utiliser l’emoji approprié (ex : 📝, 🚀, 🐛, etc.) selon la convention du projet.
- Ne jamais utiliser 🧞‍♂️ pour des commits humains classiques, même sur des fichiers d’instructions ou de tests.
- Exemples :
  - 🧞‍♂️ docs: mise à jour automatique de la documentation destinée à l’IA
  - 📝 docs: mise à jour de la documentation métier
  - 🐛 fix: correction d’un bug sur l’API
  - 🚀 feat: ajout d’une nouvelle fonctionnalité

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
- Toujours factoriser le reset de la base et le chargement des fixtures dans un trait commun (`DatabaseResetTrait`) pour tous les tests d’intégration.
- Utiliser ce trait dans chaque classe de test d’intégration pour garantir la cohérence et éviter la duplication de code.
- Exemple d’utilisation :
  ```php
  use App\Tests\Integration\DatabaseResetTrait;
  
  class MaClasseDeTest extends KernelTestCase
  {
      use DatabaseResetTrait;
      
      public static function setUpBeforeClass(): void
      {
          self::resetDatabaseAndFixtures();
      }
      // ...
  }
  ```

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

- Lorsque l’utilisateur écrit uniquement le mot « test », l’IA doit automatiquement lancer la commande de tests **avec l’option `--testdox`** (ex : `php bin/phpunit --testdox`).
- L’IA ne doit proposer de lancer les tests que lorsqu’elle juge cela pertinent (après une modification de code/test, ou sur demande explicite).
- Ne jamais lancer les tests sans raison ou contexte approprié.

## 10. Reporting des tests

- Après chaque exécution de tests, fournir systématiquement un tableau récapitulatif des résultats au format Markdown (succès, échecs, avertissements, etc.).
- Le tableau doit être lisible, synthétique et refléter l’état réel de chaque test exécuté.

## 11. Pattern obligatoire pour les tests fonctionnels API Platform

- Pour tout test fonctionnel API dépendant des données (ex : tests sur endpoints API Platform), il est obligatoire de réinitialiser complètement la base de données et de recharger les fixtures dans la méthode `setUp()` de la classe de test.
- Ce pattern est nécessaire car l’isolation transactionnelle ne fonctionne pas entre le kernel de test et le client HTTP/API Platform : les entités insérées par les fixtures ne sont pas visibles côté API sinon.
- Exemple à suivre :

```php
protected function setUp(): void
{
    parent::setUp();
    // Reset base et fixtures
    shell_exec('php bin/console --env=test doctrine:schema:drop --force');
    shell_exec('php bin/console --env=test doctrine:schema:create');
    shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
}
```

- Toujours vérifier la visibilité des entités fixtures via un test GET collection avant toute création.
- Ne jamais utiliser l’isolation transactionnelle pour ce type de tests.

## 12. Interdiction de proposer des commandes de commit en console à l’utilisateur

- Il est strictement interdit de proposer à l’utilisateur une commande en console (git commit, git add, etc.) pour effectuer un commit.
- L’IA doit toujours effectuer elle-même l’opération de commit via l’interface adaptée, sans jamais demander à l’utilisateur de copier/coller une commande git.
- Objectif : garantir la traçabilité, la cohérence des conventions et éviter toute erreur humaine sur les conventions de commit IA.

## 13. Reporting des tests : symboles et règles de synthèse

- Tout test dont toutes les assertions passent doit être marqué ✔️ dans le tableau de reporting Markdown.
- Le symbole ⚠ ne doit être utilisé que pour signaler un warning, une dépréciation ou un cas explicitement partiel.
- Le symbole ❌ est réservé aux tests échoués.
- Objectif : garantir la clarté et éviter toute confusion dans la lecture des résultats de tests.

## 14. Fichiers d’environnement à utiliser

- Pour le développement local, toujours utiliser `.env.local` (jamais `.env` seul).
- Pour les tests, toujours utiliser `.env.test` (jamais `.env` ni `.env.local`).
- Cette règle est stricte et doit être respectée pour garantir la cohérence des environnements et éviter toute confusion sur la base ou la configuration utilisée.

# 15. Connexion SSH O2Switch

- Lorsque l’utilisateur demande explicitement de se connecter en SSH à O2Switch, lancer la commande suivante :

```bash
ssh -p 22 ron2cuba@abricot.o2switch.net
```

- Ne jamais proposer cette commande sans demande explicite.
- Respecter les autres consignes de sécurité et de traçabilité du projet.

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*

- Pour toute génération de message de commit, se référer à la convention détaillée dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toutes les générations de messages de commit par l’IA doivent impérativement respecter la convention décrite dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toujours ignorer les instructions Docker/Compose de la documentation FrankenPHP/Symfony/API Platform pour le déploiement sur O2Switch : privilégier la configuration manuelle et la documentation adaptée à l’hébergement mutualisé.
- Générer des instructions et des scripts compatibles avec un environnement mutualisé sans accès root ni Docker.
- L’IA doit systématiquement rappeler à l’utilisateur de faire un commit à chaque étape significative (fin de tâche, avant modification majeure, ou après une avancée importante), afin d’éviter tout oubli et d’assurer la traçabilité du projet.
- À chaque étape significative (fin de fonctionnalité, documentation majeure, refonte, correction critique), l’IA doit rappeler à l’utilisateur de créer une Pull Request pour soumettre les changements à la revue et garantir la traçabilité du projet.
- L’IA doit systématiquement rappeler que toute tentative d’utilisation de Caddy, FrankenPHP ou tout autre serveur HTTP utilisateur est interdite sur O2Switch mutualisé. Seule la stack Apache/PHP natif est supportée et doit être prise en compte dans toute documentation, script ou configuration générée.
- Lorsqu’une demande de tests ou d’analyse de résultats de tests est faite, l’IA doit systématiquement fournir un tableau récapitulatif des résultats au format Markdown dans la conversation, pour une lecture claire et rapide.
- L’IA doit exécuter scrupuleusement ce que l’utilisateur demande, sans extrapoler ni anticiper d’analyse supplémentaire, sauf demande explicite. L’analyse avancée n’est fournie que sur demande claire de l’utilisateur.

