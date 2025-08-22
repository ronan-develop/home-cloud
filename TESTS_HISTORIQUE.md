# Historique des tests effectués

## Tests unitaires et fonctionnels

- ✅ CRUD complet sur l’entité PrivateSpace (API Platform)
- 🛡️ Validation NotBlank sur name et description (PrivateSpace)
- ✅ CRUD complet sur l’entité File (API Platform)
- 🛡️ Validation NotBlank et NotNull sur File (filename, path, size, mimeType, createdAt, privateSpace)
- 🧪 Tests d’intégration avec fixtures Alice
- 🔄 Isolation des tests avec DAMA Doctrine Test Bundle
- 🚨 Tests de validation des erreurs API (Content-Type, erreurs 422)
- 🏗️ Tests de synchronisation schéma/entités (doctrine:schema:update)
- 🚀 Tests de déploiement automatique via cPanel et .cpanel.yml

## Tests de workflow

- 🔀 Vérification du workflow PR (création, review, merge)
- 📝 Vérification du workflow de commit (conventions, emojis)
- 🚦 Vérification du workflow de déploiement (push GitHub, synchro O2Switch)

---

Pour chaque test, voir le détail dans les fichiers de test du dossier `tests/` ou dans la documentation technique.

[⬅️ Retour au README](README.md)
