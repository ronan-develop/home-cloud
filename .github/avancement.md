# 📋 Avancement — HomeCloud API

> Dernière mise à jour : 2026-03-26

> **Status git :** `main` — tout mergé, 312 tests ✅

---

## ✅ Refactoring SOLID/KISS/DRY — TERMINÉ (2026-03-16)

Toutes les vagues du plan de refactoring sont complètes. PRs mergées :

| PR | Branche | Contenu |
|----|---------|---------|
| #136 | `refactor/AuthenticationResolver` | AuthenticationResolver injecté dans FolderProcessor/AlbumProcessor/FolderProvider + IriExtractor (extraction UUID depuis IRI) |
| #137 | `feat/RepositoryInterfaces` | FolderRepositoryInterface, UserRepositoryInterface, ShareRepositoryInterface (DIP) |
| #138 | `refactor/OwnershipChecker` | OwnershipChecker — centralise les vérifications de propriété (5 occurrences éliminées) |
| #139 | `refactor/AlbumRepositoryInterface` | AlbumRepositoryInterface dans les controllers et processor |
| #140 | `refactor/FolderProcessorSRP` | FolderService extrait de FolderProcessor (SRP) + 3 interfaces DIP |
| #141 | `chore/MoveInterfacesToInterfaceDir` | FolderMoverInterface + PasswordResetServiceInterface → src/Interface/ |
| #142 | `chore/ReorganizeServiceDir` | src/Factory/ (FolderTreeFactory) + src/Security/ (AuthenticationResolver, AuthorizationChecker, ShareAccessChecker) |
| #143 | `refactor/ExceptionStyle` | InvalidArgumentException → BadRequestHttpException dans tous les services |

### Architecture finale

```
src/
├── Controller/         ← HTTP uniquement, délègue aux services
├── Factory/            ← FolderTreeFactory
├── Interface/          ← 14 contrats DIP
├── Repository/         ← accès données (implémentent les interfaces)
├── Security/           ← AuthenticationResolver, AuthorizationChecker, OwnershipChecker, ShareAccessChecker
├── Service/            ← logique métier (FolderService, AlbumService, FileActionService…)
├── State/              ← processors/providers API Platform (dispatchers HTTP → services)
└── Entity/             ← entités Doctrine
```

### Principes appliqués
- **SRP** : FolderProcessor réduit à dispatcher pur (~115 lignes, était ~260)
- **DIP** : 14 interfaces, zéro dépendance concrète dans les processors
- **DRY** : auth, ownership, IRI extraction — chacun centralisé une fois
- **Testabilité** : tous les services mockables via leurs interfaces

---

## ✅ Audit Sécurité — TERMINÉ (2026-03-26)

Score global : **9/10** — 4/5 axes de remédiation implémentés.

| Point audité | Résultat |
|---|---|
| Upload validation (MIME, extension, path traversal) | ✅ Excellent |
| JWT RS256 + Refresh Token rotation | ✅ OK |
| Autorisation (OwnershipChecker + Voter) | ✅ OK |
| Mots de passe (argon2id/bcrypt) | ✅ OK |
| CORS (regex serrée en prod) | ✅ OK |
| Security Headers (CSP, X-Frame-Options…) | ✅ OK |
| SQL injection (QueryBuilder paramétrisé) | ✅ OK |
| Rate limiting sur /api/v1/auth/login | ✅ Fait (PR #146 — 5 req / 15 min, HTTP 429) |
| Auth failure logging | ✅ Fait (PR #146 — email, IP, user-agent) |
| HSTS header | ✅ Fait (PR #146 — prod uniquement, max-age=31536000) |
| `composer audit` en CI | ✅ Fait (PR #147 — step avant les tests) |
| Assert sur DTOs | ⚠️ Basse priorité — reste à faire |

### CI/CD — Node.js 24

- `FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true` ajouté (PR #147)
- `node-version` mis à jour vers `22` (LTS)

→ Détail des tâches restantes : `.github/todo-security.md`

---

## ⚠️ Bugs connus

| Priorité | Bug | Détail |
|----------|-----|--------|
| 🟡 Moyen | **Drag & drop upload non fonctionnel** | Quand on glisse un fichier sur la zone, le navigateur l'ouvre au lieu de déclencher l'upload. L'upload via le bouton "parcourir" fonctionne. À investiguer. |

---

## 📊 État des tests

- **312 tests**, 659 assertions
- 1 skipped (test d'intégration Stopwatch conditionnel)
- 0 failures, 0 errors

---

## 🗺️ Prochaines pistes

Voir `.github/todo-api-features.md` et `.github/todo-user-settings.md` pour les fonctionnalités restantes.

### Priorité suggérée
1. **Assert sur DTOs** (clôture sécurité — priorité basse) → `.github/todo-security.md`
2. **API Features** PATCH/DELETE Folder, File, User + pagination → `.github/todo-api-features.md`
3. **Page paramètres utilisateur** (email, mot de passe) → `.github/todo-user-settings.md`

