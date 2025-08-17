# Endpoints API Home Cloud

Ce document liste les principaux endpoints exposés via API Platform (REST/GraphQL) à implémenter pour couvrir les cas d’usage métier (gestion des utilisateurs, espaces privés, fichiers, partages, droits, logs).

> L’API sera construite avec [API Platform](https://api-platform.com/), qui permet de générer automatiquement la documentation OpenAPI/Swagger, d’exposer les entités en REST et GraphQL, et de gérer la sécurité, la pagination, la validation, etc.

## Utilisateurs (User)

- `POST /api/register` : inscription d’un nouvel utilisateur
- `POST /api/login` : authentification
- `GET /api/me` : infos du profil connecté
- `PATCH /api/me` : mise à jour du profil

## Espace privé (PrivateSpace)

- `GET /api/private-space` : récupérer l’espace privé de l’utilisateur
- `PATCH /api/private-space` : modifier les infos de l’espace

## Fichiers (File)

- `GET /api/files` : lister les fichiers de l’espace privé
- `POST /api/files` : uploader un fichier
- `GET /api/files/{id}` : télécharger un fichier
- `DELETE /api/files/{id}` : supprimer un fichier

## Partage (Share)

- `POST /api/shares` : créer un partage (fichier ou global)
- `GET /api/shares` : lister les partages de l’utilisateur
- `GET /api/shares/{token}` : accéder à une ressource partagée (public/invité)
- `DELETE /api/shares/{id}` : révoquer un partage

## Droits d’accès (AccessRight)

- `PATCH /api/shares/{id}/rights` : modifier les droits d’un partage

## Logs d’accès (AccessLog)

- `GET /api/shares/{id}/logs` : consulter l’historique des accès à un partage

## Notifications

- (optionnel) Webhook ou endpoint pour notifier l’utilisateur lors d’un accès à un partage

---

> La structure et les routes exactes pourront évoluer selon la configuration d’API Platform, les besoins métier et les conventions REST/GraphQL du projet.
