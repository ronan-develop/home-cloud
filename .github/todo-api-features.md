# Todo : API Features à compléter (CRUD Folder, File, User)

## Folder

- [ ] PATCH /api/v1/folders/{id} (renommage, changement parent, validation)
- [ ] DELETE /api/v1/folders/{id} (test ownership, suppression récursive, erreurs)
- [ ] Pagination, tri, recherche sur /api/v1/folders
- [ ] Validation nom dossier (unicité, caractères interdits)
- [ ] Tests fonctionnels (CRUD, droits, erreurs)

## File

- [ ] PATCH /api/v1/files/{id} (renommage, changement de dossier)
- [ ] DELETE /api/v1/files/{id} (test ownership, suppression physique, erreurs)
- [ ] Pagination, tri, recherche sur /api/v1/files
- [ ] Validation nom fichier (unicité dans dossier, caractères interdits)
- [ ] Tests fonctionnels (CRUD, droits, erreurs, upload, download)

## User

- [ ] PATCH /api/v1/users/{id} (email, displayName, password)
- [ ] Validation forte (email unique, mot de passe fort)
- [ ] Tests fonctionnels (modification, sécurité, erreurs)

## Général

- [ ] Vérifier la couverture des DTO, Provider, Processor (SOLID, SRP)
- [ ] Respecter la dépendance aux interfaces dans les services
- [ ] Couvrir tous les cas d’erreur (404, 403, 400)
- [ ] Documentation OpenAPI à jour
