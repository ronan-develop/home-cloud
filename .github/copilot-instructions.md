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

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*

- Pour toute génération de message de commit, se référer à la convention détaillée dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toutes les générations de messages de commit par l’IA doivent impérativement respecter la convention décrite dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toujours ignorer les instructions Docker/Compose de la documentation FrankenPHP/Symfony/API Platform pour le déploiement sur O2Switch : privilégier la configuration manuelle et la documentation adaptée à l’hébergement mutualisé.
- Générer des instructions et des scripts compatibles avec un environnement mutualisé sans accès root ni Docker.
- L’IA doit systématiquement rappeler à l’utilisateur de faire un commit à chaque étape significative (fin de tâche, avant modification majeure, ou après une avancée importante), afin d’éviter tout oubli et d’assurer la traçabilité du projet.
- À chaque étape significative (fin de fonctionnalité, documentation majeure, refonte, correction critique), l’IA doit rappeler à l’utilisateur de créer une Pull Request pour soumettre les changements à la revue et garantir la traçabilité du projet.
