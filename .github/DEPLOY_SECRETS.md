# Configuration des secrets GitHub pour le déploiement

## Secrets requis (Settings → Secrets and variables → Actions)

### SSH
- **DEPLOY_SSH_USER** : nom d'utilisateur SSH (ex: `ron2cuba`)
- **DEPLOY_SSH_HOST** : hostname (ex: `lenouvel.me`)
- **DEPLOY_SSH_KEY** : clé privée SSH (contenu de `~/.ssh/id_rsa` ou dédiée)

### Déploiement
- **DEPLOY_PRENOM** : prénom pour le sous-domaine (ex: `Ronan`)
- **DEPLOY_DB_PASSWORD** : mot de passe MySQL (utilisé UNIQUEMENT au primo déploiement)

### Mail
- **MAIL_SERVER** : serveur SMTP (ex: `smtp.gmail.com`)
- **MAIL_PORT** : port SMTP (ex: `587` pour TLS, `25` pour plain)
- **MAIL_USERNAME** : utilisateur SMTP
- **MAIL_PASSWORD** : mot de passe SMTP
- **MAIL_FROM** : adresse email "from" (ex: `noreply@homecloud.example.com`)

## ⚠️ Sécurité

**JAMAIS** commiter ces valeurs dans Git. Utiliser GitHub Secrets :

1. Va dans Settings → Secrets and variables → Actions
2. Crée chaque secret (Copy-paste les valeurs)
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
