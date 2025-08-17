# TODO

## 1. API Utilisateur (approche BDD/TDD)

### a. Définir les cas d’usage métier User

- [ ] Inscription d’un nouvel utilisateur (register)
- [ ] Connexion (login)
- [ ] Déconnexion (logout)
- [ ] Consultation du profil (me)
- [ ] Mise à jour du profil (update)
- [ ] Suppression du compte (delete)
- [ ] Gestion des erreurs (email déjà utilisé, mauvais mot de passe, etc.)

### b. Lister les endpoints REST à exposer

- [ ] POST /api/register
- [ ] POST /api/login
- [ ] POST /api/logout
- [ ] GET /api/me
- [ ] PATCH /api/me
- [ ] DELETE /api/me

### c. Définir les règles métier et validations

- [ ] Unicité de l’email et du username
- [ ] Format et force du mot de passe
- [ ] Validation du format email
- [ ] Activation/désactivation du compte
- [ ] Gestion des statuts (actif, supprimé, etc.)

### d. Écrire les scénarios BDD (Gherkin ou équivalent)

- [ ] Scénario : inscription réussie
- [ ] Scénario : inscription KO (email déjà utilisé)
- [ ] Scénario : connexion réussie
- [ ] Scénario : connexion KO (mauvais mot de passe)
- [ ] Scénario : accès à /me sans être connecté
- [ ] Scénario : mise à jour du profil
- [ ] Scénario : suppression du compte

### e. Implémenter les tests fonctionnels API (TDD)

- [ ] Test POST /api/register (succès/erreur)
- [ ] Test POST /api/login (succès/erreur)
- [ ] Test POST /api/logout
- [ ] Test GET /api/me (auth/non-auth)
- [ ] Test PATCH /api/me
- [ ] Test DELETE /api/me

### f. Créer les contrôleurs/API Platform Resource pour chaque endpoint

- [ ] Contrôleur d’inscription
- [ ] Contrôleur de connexion
- [ ] Contrôleur de déconnexion
- [ ] Contrôleur profil (me)
- [ ] Contrôleur update profil
- [ ] Contrôleur suppression compte

### g. Valider la sécurité (auth, droits, tokens, etc.)

- [ ] Mise en place JWT/session
- [ ] Protection des routes sensibles
- [ ] Tests d’accès non autorisé

### h. Documenter chaque endpoint et les règles métier associées

- [ ] OpenAPI/Swagger à jour
- [ ] README/Doc métier

### i. Synchroniser la doc et les schémas après implémentation

- [ ] Vérifier la cohérence doc/code
- [ ] Mettre à jour le diagramme de classes si besoin

> Prioriser la robustesse, la sécurité et la clarté métier. Utiliser la doc OpenAPI générée pour valider l’exhaustivité. Commit et PR à chaque étape significative.

---

## 2. Modélisation métier et partage

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
