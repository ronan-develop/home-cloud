# Roadmap — HomeCloud

> Mise à jour : 2026-04-16
> État : `main` stable, 309 tests ✅

---

## 🔴 Priorité 1 — Paramètres utilisateur

**Objectif** : permettre à l'utilisateur de modifier son profil (email, displayName, mot de passe).

### API
- [ ] `PATCH /api/v1/users/{id}` — `UserProcessor::handlePatch` (email, displayName, password)
- [ ] Validation forte : email unique en base, mot de passe ≥ 8 chars
- [ ] Ownership check : un user ne peut modifier que son propre profil
- [ ] Tests fonctionnels : 200 (owner), 403 (autre user), 422 (validation), 404

### UI
- [ ] Page `/settings` — formulaire email + displayName + changement mot de passe
- [ ] Lien dans le footer sidebar (déjà présent : nom user + déconnexion)
- [ ] Toast succès/erreur, validation inline

**Fichiers concernés** :
- `src/State/UserProcessor.php` (à créer ou étendre)
- `src/ApiResource/UserOutput.php`
- `templates/web/settings.html.twig` (à créer)
- `src/Controller/Web/UserSettingsController.php` (à créer)

---

## 🟡 Priorité 2 — Assert sur les DTOs (clôture sécurité)

**Objectif** : standardiser les erreurs de validation via Symfony Validator (HTTP 422 avec violations détaillées).

- [ ] `#[Assert\NotBlank]`, `#[Assert\Length(max: 255)]` sur les champs texte des ApiResource
- [ ] `#[Assert\Email]` sur `UserOutput::$email`
- [ ] Vérifier que API Platform retourne 422 avec détail des violations
- [ ] Tests : payload invalide → 422 avec message explicite

**Fichiers concernés** :
- `src/ApiResource/UserOutput.php`
- `src/ApiResource/FolderOutput.php`
- `src/ApiResource/FileOutput.php`

---

## 🟢 Priorité 3 — Pagination & tri

**Objectif** : éviter de charger tous les dossiers/fichiers en une seule requête.

- [ ] Pagination sur `GET /api/v1/folders` (API Platform `Paginator` natif)
- [ ] Pagination sur `GET /api/v1/files`
- [ ] Paramètre `?folderId=` déjà présent sur files — vérifier qu'il fonctionne avec pagination
- [ ] Tri par nom, date de création

---

## 🔵 Priorité 4 — Qualité & documentation

- [ ] Validation unicité nom dossier dans un même parent (déjà partiellement fait, à compléter)
- [ ] Validation unicité nom fichier dans un même dossier
- [ ] Documentation OpenAPI à jour (descriptions, exemples de réponse)
- [ ] Bug : drag & drop upload ouvre le fichier dans le navigateur au lieu de déclencher l'upload

---

## ✅ Déjà fait (référence)

| Feature | PR |
|---------|-----|
| CRUD complet Folder (PATCH/DELETE + tests) | #136–#143 |
| CRUD complet File (PATCH/DELETE + tests) | #159 |
| Audit sécurité 9/10 (JWT, rate limit, HSTS, logging) | #146–#147 |
| RenameModal (remplace prompt()) | #162 |
| Cleanup code mort | #163 |
| Icône home breadcrumb | #149 |
