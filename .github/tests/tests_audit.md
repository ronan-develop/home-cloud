# Audit détaillé des tests PHPUnit

Date : 5 mars 2026

## Résumé global

- Total des tests exécutés : 212
- La majorité des erreurs sont des cas attendus (404, 400, 403, etc.)
- Aucun échec critique inattendu
- Les exceptions sont principalement des scénarios d’erreur API (NotFound, BadRequest, AccessDenied)

## Détail des erreurs rencontrées

### AlbumProcessor

- NotFoundHttpException : "User not found" (ligne 97)
- BadRequestHttpException : "name is required" (ligne 89)
- BadRequestHttpException : "Invalid characters in album name" (ligne 121)
- AccessDeniedHttpException : "You are not the owner of this album" (lignes 116, 139)

### AlbumAddMediaController

- NotFoundHttpException : "Album not found" (ligne 42)
- NotFoundHttpException : "Media not found" (ligne 52)

### AlbumRemoveMediaController

- NotFoundHttpException : "Album not found" (ligne 36)

### FileProcessor

- NotFoundHttpException : "Target folder not found" (ligne 136)
- AccessDeniedHttpException : "You do not own the target folder" (ligne 139)
- UnauthorizedHttpException : "Authentication required" (ligne 122)
- BadRequestHttpException : "A file must be uploaded (multipart field: 'file')" (ligne 69)

### FolderProcessor

- AccessDeniedHttpException : "You are not the owner of this folder" (lignes 141, 225)
- NotFoundHttpException : "Not Found" (plusieurs occurrences)
- BadRequestHttpException : "name is required" (ligne 76)
- BadRequestHttpException : "A folder with this name already exists in the parent" (lignes 104, 154)
- BadRequestHttpException : "Invalid characters in folder name" (lignes 82, 146)
- NotFoundHttpException : "Parent folder not found" (lignes 94, 178)
- BadRequestHttpException : "A folder cannot be its own parent" (ligne 175)
- BadRequestHttpException : "Moving this folder would create a cycle" (ligne 185)
- AccessDeniedHttpException : "You do not own the target parent folder" (ligne 181)

### MediaProvider

- NotFoundHttpException : "Media not found" (ligne 39)
- BadRequestHttpException : "Invalid type 'invalid_type'" (ligne 49)

### MediaThumbnailController

- NotFoundHttpException : "No thumbnail available for this media" (ligne 46)
- NotFoundHttpException : "Media not found" (ligne 43)

### ShareProcessor / ShareProvider

- NotFoundHttpException : "Utilisateur invité introuvable." (ligne 67)
- BadRequestHttpException : "resourceType invalide. Valeurs acceptées : file, folder, album." (ligne 72)
- BadRequestHttpException : "permission invalide. Valeurs acceptées : read, write." (ligne 82)
- AccessDeniedHttpException : "Accès interdit à ce partage." (ligne 45)
- AccessDeniedHttpException : "Seul le propriétaire peut modifier ou supprimer ce partage." (ligne 144)

### FileProvider

- AccessDeniedHttpException : "" (ligne 57)

### AlbumWebController

- BadRequestHttpException : "Le nom de l'album est obligatoire." (ligne 52)

### ExceptionListener

- AccessDeniedHttpException : "Access Denied." (ligne 126)

### FileWebController

- BadRequestHttpException : "File type '.php' is not allowed." (ligne 87)
- AccessDeniedHttpException : "Vous ne pouvez pas supprimer ce fichier." (ligne 126)

### AbstractController

- NotFoundHttpException : "Média introuvable." (ligne 326)

## Conclusion

- Les erreurs sont cohérentes avec les cas limites testés (sécurité, validation, droits, etc.)
- Aucun bug bloquant détecté
- Suite de tests considérée comme saine à ce stade
