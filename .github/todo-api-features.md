# Todo : API Features à compléter (CRUD Folder, File, User)

## Folder

- [x] PATCH /api/v1/folders/{id} (renommage, changement parent, validation, ownership) — `FolderProcessor::handlePatch`
- [x] DELETE /api/v1/folders/{id} (ownership, suppression récursive, migration fichiers) — `FolderProcessor::handleDelete`
- [x] Tests fonctionnels PATCH/DELETE — `FolderCrudTest`, `DeleteFolderOptionsTest`
- [ ] Pagination, tri, recherche sur /api/v1/folders
- [ ] Validation unicité nom dossier dans un même parent

## File

- [x] PATCH /api/v1/files/{id} (renommage, changement de dossier, ownership) — `FileProcessor::handlePatch`
- [x] DELETE /api/v1/files/{id} (ownership check, suppression physique + thumbnail) — PR #159
- [x] Tests fonctionnels PATCH — `FileMoveTest`
- [x] Tests fonctionnels DELETE — `FileDeleteTest` (204/403/404) — PR #159
- [ ] Pagination, tri, recherche sur /api/v1/files
- [ ] Validation unicité nom fichier dans un même dossier

## User

- [ ] PATCH /api/v1/users/{id} (email, displayName, password)
- [ ] Validation forte (email unique, mot de passe fort)
- [ ] Tests fonctionnels (modification, sécurité, erreurs)

## Sécurité

- [ ] Assert sur DTOs (`#[Assert\NotBlank]`, `#[Assert\Length]`, `#[Assert\Email]`) — priorité basse

## Général

- [ ] Documentation OpenAPI à jour
