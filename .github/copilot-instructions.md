---
applyTo: '**'
---

# Instructions IA – Contexte projet Home Cloud

## Résumé du contexte

- Projet : Home Cloud (cloud privé multi-tenant, Symfony, O2Switch)
- Hébergement mutualisé, pas d’accès root, pas de Docker
- Un Caddy (reverse proxy) gère lenouvel.me et tous les sous-domaines
- Chaque sous-domaine = un espace privé, une base dédiée (nomenclature : hc_<username>)
- Gestion fine des credentials (SSH, BDD) dans des fichiers locaux non versionnés
- Documentation centralisée dans `.github/projet-context.md`
- Distribution serveur : CentOS 8 (CloudLinux, kernel 4.18.x) – cf. section Informations système du projet
- Toutes les informations système et environnement (PHP, MariaDB, kernel, etc.) sont synchronisées avec `.github/projet-context.md`

## Points clés à retenir

- Ne jamais stocker de credentials dans le dépôt
- Automatiser la création des environnements utilisateurs une fois le projet stabilisé
- Centraliser la configuration Caddy (multi-sous-domaines) sur le serveur principal
- Utiliser la logique multi-tenant Symfony côté applicatif, pas côté serveur web
- Documenter chaque étape technique et chaque contrainte dans `.github/`
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur

## TODO IA

- Garder en mémoire la roadmap d’automatisation (provisioning, rotation credentials)
- S’assurer que toute nouvelle doc ou script respecte la sécurité et la maintenabilité
- Mettre à jour ce fichier à chaque évolution majeure du contexte, de l’architecture ou de l’environnement serveur (ex : changement de distribution, upgrade PHP/MariaDB, modification des contraintes O2Switch)

---

*Ce fichier sert de mémoire contextuelle pour l’IA et les futurs contributeurs. Synchroniser avec `.github/projet-context.md` en cas de modification du contexte technique ou serveur.*

- Pour toute génération de message de commit, se référer à la convention détaillée dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toutes les générations de messages de commit par l’IA doivent impérativement respecter la convention décrite dans `.github/CONVENTION_COMMITS.md` (format, types, emojis, exemples).
- Toujours ignorer les instructions Docker/Compose de la documentation FrankenPHP/Symfony/API Platform pour le déploiement sur O2Switch : privilégier le mode binaire utilisateur, la configuration manuelle et la documentation adaptée à l’hébergement mutualisé.
- Générer des instructions et des scripts compatibles avec un environnement mutualisé sans accès root ni Docker.
