---
applyTo: '**'
---

# Instructions IA – Contexte projet Home Cloud

## Résumé du contexte

- Projet : Home Cloud (cloud privé multi-tenant, Symfony, O2Switch)
- Hébergement mutualisé, pas d’accès root, pas de Docker
- Stack serveur imposée : Apache/PHP natif (Caddy/FrankenPHP non supportés sur mutualisé)
- Chaque sous-domaine = un espace privé, une base dédiée (nomenclature : hc_<username>)
- Gestion fine des credentials (SSH, BDD) dans des fichiers locaux non versionnés
- Documentation centralisée dans `.github/projet-context.md`
- Distribution serveur : CentOS 8 (CloudLinux, kernel 4.18.x) – cf. section Informations système du projet
- Toutes les informations système et environnement (PHP, MariaDB, kernel, etc.) sont synchronisées avec `.github/projet-context.md`

## Points clés à retenir

- Ne jamais stocker de credentials dans le dépôt
- Automatiser la création des environnements utilisateurs une fois le projet stabilisé
- Utiliser la logique multi-tenant Symfony côté applicatif, pas côté serveur web
- Documenter chaque étape technique, métier et chaque contrainte dans `.github/`
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur
- Privilégier la documentation métier (README, diagrammes, cas d’usage) et la traçabilité des choix techniques

## API & Modélisation

- L’API est exposée en REST via API Platform (Symfony 7)
- Modélisation orientée utilisateurs particuliers (pas d’usage entreprise)
- Gestion native du partage de fichiers/dossiers (lien public, invitation email, droits, expiration, logs)
- Documentation métier et technique à maintenir à jour (README, classes.puml, api_endpoints.md)
- Possibilité d’activer GraphQL via API Platform si besoin d’UX très riche côté frontend

## TODO IA

- Garder en mémoire la roadmap d’automatisation (provisioning, rotation credentials)
- S’assurer que toute nouvelle doc ou script respecte la sécurité, la maintenabilité et la compatibilité mutualisé
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur (ex : changement de distribution, upgrade PHP/MariaDB, modification des contraintes O2Switch)

## Règles pour les listes de tâches (tasklists)

- Toutes les listes de tâches générées par l’IA doivent suivre ce format Markdown précis :

```
- [ ] Ajouter les contraintes de validation Symfony sur l’entité PrivateSpace
- [ ] Adapter les tests pour vérifier le code 422 sur les erreurs de validation
- [ ] Investiguer et corriger la non-disponibilité immédiate des entités créées dans les tests
- [ ] Améliorer la documentation métier et technique liée à PrivateSpace
```

- Ne jamais utiliser d’autres formats, puces ou numérotations pour les tâches à réaliser.
- Toujours entourer la liste de tâches de triples backticks Markdown si elle est affichée dans la conversation.
- Toutes les listes de tâches (tasklists) générées doivent impérativement suivre le format Markdown ci-dessous, sans puces, numérotation ou autre format :

```
- [ ] Tâche 1
- [ ] Tâche 2
```

- Toujours entourer la liste de tâches de triples backticks Markdown dans la conversation.

---

# Workflow de gestion des tests et conventions de commit/PR

## Workflow recommandé pour toute refonte ou correction majeure des tests :

1. Créer une branche dédiée à la sauvegarde de l’état initial des tests (ex : `test/snapshot-private-space-before-refacto-client`).
2. Committer l’état actuel des tests avant toute modification.
3. Ouvrir une Pull Request pour tracer ce snapshot.
4. Effectuer la refonte ou correction sur une nouvelle branche à partir de ce snapshot.
5. Committer et ouvrir une nouvelle Pull Request pour la refonte, en liant les deux PR pour assurer la traçabilité.

Ce workflow garantit la traçabilité, la possibilité de rollback et la revue efficace des évolutions de tests.

## Convention pour les messages de commit et PR

- Quand l’utilisateur demande « prépare le commit » ou « commit », l’IA doit générer un message de commit conforme à `.github/CONVENTION_COMMITS.md` avec :
  - Le message formaté
  - Les labels
  - Les #tags
  - Le numéro de la PR associée si connu

- Quand l’utilisateur demande « PR » ou « prépare la PR », l’IA doit générer :
  - Le titre de la PR
  - Le message/description détaillée
  - Les labels
  - Les #tags
  - Le numéro de la PR associée si connu

- L’IA doit systématiquement rappeler ces conventions et automatiser la génération de ces éléments pour gagner du temps et garantir la traçabilité.

- Toujours respecter la convention de commit du projet et synchroniser ces instructions si la convention évolue.

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
