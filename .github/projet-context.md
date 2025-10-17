# Contexte et Objectifs du projet Home Cloud

## Objectif principal

Développer une application web Symfony Home Cloud (interface utilisateur et logique métier) dédiée à chaque sous-domaine utilisateur, installable et maintenable via Composer depuis un dépôt privé hébergé sur O2Switch. Objectif : stocker, partager et synchroniser photos et documents, sans dépendre des services Apple, Google, etc.

## Architecture cible

- Une instance Symfony distincte pour chaque sous-domaine (ex : ronan.lenouvel.me, elea.lenouvel.me), chaque espace étant totalement indépendant
- Installation et mise à jour de chaque instance via Composer (`composer create-project` ou `composer update`) depuis un dépôt Git privé sur O2Switch
- Chaque instance dispose de sa propre base de données MySQL dédiée, garantissant l’isolation stricte des données
- Domaine principal : lenouvel.me (jamais accessible directement)
- Sous-domaines pour chaque membre du foyer (ex : ronan.lenouvel.me, elea.lenouvel.me, yannick.lenouvel.me)
- Déploiement, configuration et maintenance gérés séparément pour chaque application

## Contraintes techniques O2Switch

- Accès SSH utilisateur (pas root)
- Pas de Docker, pas d’installation de paquets système
- Serveur web et PHP gérés par l’hébergeur
- Utilisation de la console Symfony, composer, npm, pip, etc. possible
- Déploiement via git/CI/CD ou Composer possible
- Bases de données MySQL illimitées
- Espace disque illimité

## Fonctionnalités attendues

- Interface web intuitive pour le stockage et le partage de fichiers/photos
- Gestion fine des utilisateurs et permissions (par instance)
- Synchronisation multi-appareils
- Isolation stricte des espaces utilisateurs (aucune donnée partagée entre sous-domaines)
- API RESTful exposée via API Platform uniquement si besoin, pour chaque instance

## Pourquoi Symfony + Composer ?

- Symfony assure la robustesse pour la gestion HTTP, la sécurité, l’upload, et l’intégration avec API Platform si nécessaire
- Composer permet une installation et une maintenance centralisée, facilitant les mises à jour sur chaque sous-domaine
- Large écosystème et support
- Adapté aux contraintes O2Switch

## Points d’attention

- Pas d’accès root, pas de customisation serveur profonde
- Pas de containers, pas de reverse proxy custom
- Tout doit fonctionner dans les limites d’un mutualisé PHP classique
- Maintenance et mises à jour à prévoir pour chaque instance séparément
- Configuration spécifique à chaque sous-domaine (BDD, .env, etc.)

## Convention de nommage des bases de données utilisateurs

- Format : `ron2cuba_hc_<username>`
  - `ron2cuba` : préfixe imposé par O2Switch
  - `hc` : identifiant du projet Home Cloud
  - `<username>` : identifiant unique de l’utilisateur (ex : ronan, elea, yannick)
- Exemple pour toi : `ron2cuba_hc_ronan`
- À appliquer systématiquement pour chaque nouvel utilisateur

---

*Document généré automatiquement pour servir de référence projet et onboarding rapide.*
