# Todo : Sécurité — Remédiation post-audit

> Audit réalisé le 2026-03-24 — Score initial : 8/10
> Aucun point critique. Ce plan couvre les 4 axes d'amélioration identifiés.

---

## ✅ Priorité HAUTE

### 1. Rate Limiting sur `/api/v1/auth/login` — FAIT (PR #146)

**Risque :** Brute force sur l'endpoint de login — aucun mécanisme de blocage en place.

- [x] `composer require symfony/rate-limiter`
- [x] Configurer un limiter dans `config/packages/rate_limiter.yaml` (ex : 5 tentatives / 15 min par IP)
- [x] Activer dans `config/packages/security.yaml` → firewall `login` → `login_throttling`
- [x] Écrire un test fonctionnel : `testLoginIsThrottledAfterFiveFailures` (RED → GREEN)
- [x] Vérifier que le test existant `AuthTest` n'est pas cassé

---

## ✅ Priorité MOYENNE

### 2. HSTS Header en production — FAIT (PR #146)

**Risque :** Sans `Strict-Transport-Security`, un attaquant peut forcer une connexion HTTP.

- [x] Ajouter dans `SecurityHeadersListener.php` : `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- [x] Conditionner à l'env `prod` uniquement (pas en dev/test — HTTPS non disponible)
- [x] Écrire un test : vérifier que le header est présent sur une réponse API en env `prod`

---

### 3. Logging des tentatives d'authentification échouées — FAIT (PR #146)

**Risque :** Aucune traçabilité des accès refusés — impossible de détecter une attaque a posteriori.

- [x] Créer `AuthenticationFailureListener` écoutant `lexik_jwt_authentication.on_authentication_failure`
- [x] Logger : email tenté, IP client, timestamp, user-agent
- [x] Logger également les `AccessDeniedHttpException` dans un `ExceptionListener` dédié (ou l'existant)
- [x] Enregistrer le listener dans `services.yaml` avec le tag event
- [x] Écrire un test : vérifier que le logger est appelé lors d'un login échoué

---

### 4. `composer audit` dans le pipeline CI/CD — FAIT (PR #147)

**Risque :** Une dépendance vulnérable peut passer inaperçue sans vérification automatique.

- [x] Ajouter un step dans `.github/workflows/ci.yml` :
  ```yaml
  - name: 🔍 Audit des dépendances
    run: composer audit
  ```
- [x] Placer ce step avant les tests (fail fast)
- [x] Documenter dans `avancement.md` que le pipeline intègre l'audit de dépendances

---

## 🟢 Priorité BASSE

### 5. Contraintes `Assert` sur les DTOs / ApiResource inputs

**Risque :** Faible (validation métier déjà en place dans les Services), mais les messages d'erreur sont moins standardisés qu'avec le Symfony Validator.

- [ ] Identifier les champs éditables via PATCH dans les entités `File`, `Folder`, `User`
- [ ] Ajouter `#[Assert\NotBlank]`, `#[Assert\Length(max: 255)]` sur les champs texte
- [ ] Ajouter `#[Assert\Email]` sur `User::$email`
- [ ] Vérifier que API Platform retourne des erreurs 422 avec violations détaillées
- [ ] Écrire des tests : payload invalide → 422 avec message explicite

---

## 📋 Résumé

| # | Tâche | Priorité | Difficulté estimée |
|---|-------|----------|--------------------|
| 1 | Rate limiting login | 🔴 Haute | Facile (bundle natif Symfony) |
| 2 | HSTS header | 🟡 Moyenne | Trivial (1 ligne de code) |
| 3 | Auth failure logging | 🟡 Moyenne | Facile (listener + PSR-3) |
| 4 | `composer audit` en CI | 🟡 Moyenne | Trivial (1 step YAML) |
| 5 | Assert sur DTOs | 🟢 Basse | Moyen (plusieurs entités) |
