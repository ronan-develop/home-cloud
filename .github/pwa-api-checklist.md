# 📋 PWA HomeCloud — Checklist technique API

> Ce fichier définit toutes les étapes à suivre pour intégrer la PWA avec l’API HomeCloud. Chaque point doit être validé avant passage en production.

---

## 1. Authentification JWT

- [ ] Appel POST /api/v1/auth/login (récupération du token)
- [ ] Stockage sécurisé du token JWT (localStorage ou IndexedDB)
- [ ] Refresh token via POST /api/v1/auth/token/refresh
- [ ] Gestion expiration/rotation du token
- [ ] Déconnexion (suppression du token, reset session PWA)

## 2. Appels API sécurisés

- [ ] Utilisation du header Authorization: Bearer <token> sur tous les fetch
- [ ] Gestion des erreurs API (401, 403, 404, 500)
- [ ] Retry automatique en cas d’expiration du token

## 3. Synchronisation offline

- [ ] Service worker : cache des assets et des réponses API
- [ ] File queue pour les uploads offline (stockage temporaire, retry)
- [ ] Gestion des conflits lors de la synchronisation

## 4. Upload & Download fichiers

- [ ] Upload multipart via FileUploadController (POST /api/v1/files)
- [ ] Téléchargement via FileDownloadController (GET /api/v1/files/{id}/download)
- [ ] Vérification Content-Type et Content-Disposition

## 5. Pagination & filtrage

- [ ] Utilisation des endpoints paginés (folders, files, medias, albums)
- [ ] Filtrage par dossier, type, utilisateur

## 6. Gestion des partages

- [ ] Appels API /api/v1/shares pour création, consultation, suppression
- [ ] Vérification des permissions (read/write, expiration)

## 7. Sécurité

- [ ] CORS configuré sur l’API
- [ ] Headers HTTP de sécurité (nosniff, CSP, etc.)
- [ ] Validation côté client des données envoyées

## 8. Refresh automatique du token

- [ ] Service worker ou client JS : refresh du token avant expiration
- [ ] Gestion des erreurs de refresh

## 9. Tests fonctionnels API

- [ ] Vérification de tous les cas d’usage PWA (login, upload, download, partage, offline)
- [ ] Tests automatisés (PHPUnit + Cypress ou Playwright)

---

> Ce fichier doit être suivi à la lettre pour garantir la robustesse et la sécurité de la PWA HomeCloud.
