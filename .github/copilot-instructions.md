---
applyTo: '**'
description: 'Instructions IA optimisées pour le projet Home Cloud hébergé sur O2Switch mutualisé, avec Symfony et API Platform.'
---

# 🧞‍♂️ Instructions IA – Home Cloud

Tu créés/écrits dans les fichiers suivants quand je te le demande expressément

## 📋 Sommaire rapide
- [⚡ Règles critiques](#-règles-critiques---accès-rapide)
- [🎯 Commandes fréquentes](#-commandes-fréquentes---accès-rapide)
- [🐘 Spécificités PHP/Symfony](#-spécificités-phpsymfony---agent-optimisé)
- [🏗️ Contexte architecture](#️-contexte-et-architecture)
- [🔄 Workflow & Tests](#-workflow--tests)
- [📚 Références](#-références-rapides)

---

## ⚡ Règles critiques - Accès rapide

### 🚫 Interdictions absolues
- **Jamais** de credentials dans le dépôt
- **Jamais** Docker/Caddy/FrankenPHP sur O2Switch (Apache/PHP natif uniquement)
- **Jamais** proposer `git commit` en console à l'utilisateur (IA fait le commit)
- **Jamais** `.env.local` pour les tests (`.env.test` uniquement)
- **Jamais** extrapoler sans demande explicite de l'utilisateur

### 🎯 Actions automatiques obligatoires
- **Mot "test" seul** → `php bin/phpunit --testdox`
- **Emoji 🧞‍♂️** obligatoire pour toute consigne IA
- **Tableau Markdown** après chaque exécution de tests
- **Rappel commit/PR** à chaque étape significative
- **Pattern setUp()** obligatoire pour tests API Platform

### 📊 Symboles reporting tests
- **✔️** = Test réussi (toutes assertions OK)
- **⚠️** = Warning/dépréciation uniquement  
- **❌** = Test échoué

---

## 🎯 Commandes fréquentes - Accès rapide

### Tests (auto-déclenchement)
```bash
# Lancement automatique si utilisateur écrit "test"
php bin/phpunit --testdox

# Setup base test (si nécessaire)
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:schema:create
php bin/console --env=test hautelook:fixtures:load --no-interaction
```
### SSH O2Switch (sur demande explicite uniquement)
```bash
ssh -p 22 ron2cuba@abricot.o2switch.net
```
Environnement de fichiers : 
- Développement : `.env.local`
- Tests : `.env.test` (ne pas modifier)
- Base test : suffixe _test obligatoire

### 🐘 Spécificités PHP/Symfony - Agent optimisé

#### Pattern obligatoire tests API Platform
```php

// OBLIGATOIRE pour tout test fonctionnel API dépendant des données
protected function setUp(): void
{
    parent::setUp();
    // Reset complet base + fixtures (isolation transactionnelle ne fonctionne pas avec API Platform)
    shell_exec('php bin/console --env=test doctrine:schema:drop --force');
    shell_exec('php bin/console --env=test doctrine:schema:create');
    shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
}
```
#### Structure tests recommandée
```txt
tests/
├── Unit/           # Tests unitaires
├── Integration/    # Tests d'intégration  
└── Application/    # Tests fonctionnels (API, HTTP, E2E)
```

#### Traits communs recommandés
```php
// App\Tests\Integration\DatabaseResetTrait
trait DatabaseResetTrait
{
    public static function resetDatabaseAndFixtures(): void
    {
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
    }
}

// Utilisation dans les tests
use App\Tests\Integration\DatabaseResetTrait;

class MaClasseDeTest extends KernelTestCase
{
    use DatabaseResetTrait;
    
    public static function setUpBeforeClass(): void
    {
        self::resetDatabaseAndFixtures();
    }
}
```

#### Installation test-pack obligatoire
```bash
composer require --dev symfony/test-pack
composer require --dev dama/doctrine-test-bundle  # Pour isolation
```

#### Configuration isolation PHPUnit
```xml
<!-- phpunit.dist.xml -->
<phpunit>
  <extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
  </extensions>
</phpunit>
```

### 🏗️ Contexte et architecture

Stack technique
- Framework : Symfony 7 + API Platform
- Hébergement : O2Switch mutualisé (Apache/PHP natif)
- Architecture : Multi-tenant, chaque sous-domaine = espace privé
- Base données : MySQL, base dédiée par tenant (hc_<username>)
- Contraintes hébergement O2Switch
  ❌ Pas de Docker/root/Caddy/FrankenPHP
  ✅ Apache/PHP natif uniquement
  ✅ Scripts compatibles mutualisé
  ✅ Configuration manuelle privilégiée
- Modélisation métier
  - Cible : Particuliers
  - Fonctionnalités : Partage fichiers/dossiers, gestion droits, logs, expiration
API : REST (API Platform)

### 🔄 Workflow & Tests

#### Workflow snapshot obligatoire
1. Créer branche snapshot (test/snapshot-...)
2. Commit état initial
3. PR snapshot vers branche d'origine
4. Refonte sur nouvelle branche
5. PR refonte liée à la PR snapshot
6. Label snapshot
   - Label : snapshot
   - Couleur : #6f42c1 (violet)
   - Description : Snapshot d'état avant refonte/évolution majeure

#### Règles tests
- Indépendance : Chaque test exécutable seul
- Reproductibilité : Résultats identiques à chaque run
- Vérification fixtures : Toujours tester GET collection avant création
- Debug : Dumper réponse brute si collection vide/inattendue
- Base dédiée : Suffixe _test obligatoire
- Fixtures : Via Alice ou DoctrineFixturesBundle

#### Format de tasklist obligatoire
```markdown
- [ ] Tâche 1
- [ ] Tâche 2
- [x] Tâche 3 complétée
```
#### Bonnes pratiques tests & environnement
- Toujours installer le test-pack Symfony pour bénéficier de PHPUnit, BrowserKit, etc.
- Le kernel de test est défini par la variable d'environnement KERNEL_CLASS dans .env.test
- Pour garantir l'isolation, utiliser RefreshDatabaseTrait ou DAMA\DoctrineTestBundle pour rollback automatique
- Les tests doivent être reproductibles, indépendants et ne jamais dépendre de l'état d'un autre test
- Ne jamais utiliser .env.local en environnement de test (non pris en compte)

#### Configuration base de test et isolation

- Toujours utiliser une base dédiée pour les tests, suffixée _test
- Pour un setup partagé, configurez la base dans .env.test (commit au dépôt)
- Utiliser des transactions pour isoler les tests
- Pour un setup local, surcharger dans .env.test.local (non versionné)
- Création de la base et du schéma :
```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:schema:create
```
- Chargement des fixtures :
```bash
php bin/console --env=test doctrine:fixtures:load
## ou selon le contexte
php bin/console --env=test hautelook:fixtures:load --no-interaction
```
- Nettoyer les données après chaque test (via des fixtures ou des méthodes de nettoyage)

### 📚 Références rapides

- **Documentation centrale**
- **Conventions commits** : `.github/CONVENTION_COMMITS.md`
- **Contexte projet** : `.github/projet-context.md`
- **Endpoints API** : `api_endpoints.md`
- **Architecture** : `classes.puml`

#### Règle emoji IA 🧞‍♂️
- Obligatoire pour toute modification/ajout de consigne IA
- Interdit pour commits humains classiques
- Scope : Copilot, agents IA, documentation IA
- Toute modification, ajout ou clarification d'une consigne, règle ou documentation destinée à l'IA doit être committée avec l'emoji 🧞‍♂️, même si ce n'est pas généré par Copilot

#### Rappels automatiques IA
- Commit à chaque étape significative
- PR pour soumission à la revue
- Tests avec tableau récapitulatif Markdown
- Traçabilité et documentation métier/technique
- L'IA doit systématiquement rappeler à l'utilisateur de faire un commit à chaque étape significative
- À chaque étape significative, l'IA doit rappeler à l'utilisateur de créer une Pull Request

#### Bonnes pratiques sécurité
- Privilégier traçabilité et documentation
- Éviter tout credential en dur
- Respecter contraintes hébergement mutualisé
- Synchroniser documentation à chaque évolution majeure

#### Comportement IA
- L'IA doit exécuter scrupuleusement ce que l'utilisateur demande, sans extrapoler ni anticiper d'analyse supplémentaire, sauf demande explicite
- L'analyse avancée n'est fournie que sur demande claire de l'utilisateur
- L'IA ne doit proposer de lancer les tests que lorsqu'elle juge cela pertinent (après une modification de code/test, ou sur demande explicite)
- Ne jamais lancer les tests sans raison ou contexte approprié

#### Reporting et documentation
- Après chaque exécution de tests, fournir systématiquement un tableau récapitulatif des résultats au format Markdown
- Le tableau doit être lisible, synthétique et refléter l'état réel de chaque test exécuté
- Lorsqu'une demande de tests ou d'analyse de résultats de tests est faite, l'IA doit systématiquement fournir un tableau récapitulatif des résultats au format Markdown dans la conversation
- Toujours utiliser le format Markdown strict pour les listes de tâches
- Toujours entourer la liste de tâches de triples backticks

#### Contraintes spécifiques
- Toujours ignorer les instructions Docker/Compose de la documentation FrankenPHP/Symfony/API Platform pour le déploiement sur O2Switch
- Privilégier la configuration manuelle et la documentation adaptée à l'hébergement mutualisé
- Générer des instructions et des scripts compatibles avec un environnement mutualisé sans accès root ni Docker
- L'IA doit systématiquement rappeler que toute tentative d'utilisation de Caddy, FrankenPHP ou tout autre serveur HTTP utilisateur est interdite sur O2Switch mutualisé

#### 🎯 Méta-instructions pour l'agent
- Priorités d'accès mémoire
  - Cache permanent : Règles critiques, commandes fréquentes
  - Cache session : Patterns PHP, workflow tests
  - Cache contextuel : Architecture, références
- Comportement optimal
  - Exécution stricte des demandes utilisateur
  - Pas d'extrapolation sauf demande explicite
  - Rappels proactifs commit/PR/tests
  - Respect absolu des interdictions O2Switch
- Performance
  - Accès ultra-rapide aux règles critiques
  - Pattern tests en mémoire prioritaire
  - Conventions commits via référence `.github/CONVENTION_COMMITS.md`
  - Optimisation pour usage fréquent commande "test"