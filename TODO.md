# TODO

- [ ] Affiner la modélisation métier (diagramme de classes)
  - [ ] Détailler les entités principales (User, PrivateSpace, Database, Share)
  - [ ] Ajouter l'entité File (gestion des fichiers partagés)
  - [ ] Définir les relations et règles de gestion (propriétaire, partage, accès invité)
- [ ] Décrire les cas d'usage de partage
  - [ ] Partage par lien public (avec ou sans expiration)
  - [ ] Partage par invitation email (utilisateur existant ou externe)
  - [ ] Gestion des droits d'accès (lecture seule, modification, suppression)
  - [ ] Notifications et suivi des accès partagés
- [ ] Mettre à jour la documentation technique et métier à chaque évolution
- [ ] Réaliser un schéma d'architecture API + PWA
  - [ ] Backend : API Symfony avec ApiPlatform (multi-tenant)
  - [ ] Frontend : PWA (Vue, Angular ou React à choisir)
  - [ ] Décrire les flux, la sécurité, et la séparation des responsabilités
