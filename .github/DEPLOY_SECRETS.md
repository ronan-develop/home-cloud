# Configuration des secrets GitHub pour le déploiement

> **État (2026-07-20) : secrets présents mais inutilisés.** `gh secret list` confirme que `DEPLOY_SSH_*`, `DEPLOY_PRENOM`, `DEPLOY_DB_PASSWORD` (créés 2026-03-08) et `DEPLOY_WEBHOOK_SECRET`/`DEPLOY_WEBHOOK_URL` (créés 2026-04-11) existent bien côté GitHub — mais aucun des deux mécanismes qu'ils devaient alimenter n'est actif : `deploy.yml` n'a jamais été committé (seul `ci.yml` existe), et le webhook `public/deploy.php` reçoit une signature invalide depuis plusieurs jours (401 sur toutes les livraisons récentes). Le déploiement réel passe par `bin/deploy-all.sh` en SSH manuel — voir `.claude/deploiement.md`.

## Secrets requis (Settings → Secrets and variables → Actions)

### SSH
- **DEPLOY_SSH_USER** : `ron2cuba` ✅ créé
- **DEPLOY_SSH_HOST** : `lenouvel.me` ✅ créé
- **DEPLOY_SSH_KEY** : clé privée SSH ✅ créé

### Déploiement
- **DEPLOY_PRENOM** : `ronan` ✅ créé
- **DEPLOY_DB_PASSWORD** : mot de passe MySQL ✅ créé

## ⚠️ Sécurité

**JAMAIS** commiter ces valeurs dans Git. Utiliser GitHub Secrets :

1. Va dans Settings → Secrets and variables → Actions
2. Les secrets sont déjà créés via GitHub CLI
3. Le workflow les charge via `${{ secrets.XXX }}`
4. Les valeurs sont masquées dans les logs

## Utilisation

### Déploiement manuel
```bash
gh workflow run deploy.yml
```

Ou via GitHub UI : Actions → Deploy → Run workflow

### Mode automatique (optional)
Pour auto-déclencher après succès CI, ajouter dans `ci.yml` :
```yaml
needs: [ php, js ]
if: github.ref == 'refs/heads/main' && github.event_name == 'push'
```
(Pas activé par défaut pour éviter les déploiements surprises)
