# TODO — HomeCloud

## Fait

### 1. Fixer la file Messenger (20 messages en attente) ✅

- [x] Vérifier l'état actuel : `php bin/console messenger:stats` → 20 en attente, 0 échec
- [x] Consommer les 20 `MediaProcessMessage` en attente : `php bin/console messenger:consume async --time-limit=60 --limit=20`
- [x] File vide confirmée : `messenger:stats` → async 0, failed 0

### 2. Déclarer ext-gd et ext-exif dans composer.json ✅

- [x] Ajouté `"ext-exif": "*"` et `"ext-gd": "*"` à la clé `require`
- [x] `composer update --lock` pour resynchroniser le lock (sans bump de versions)
- [ ] Commit : `fix: déclarer ext-gd et ext-exif comme dépendances obligatoires`

### 3. Cron o2switch (maintenir le worker vivant) ✅

- [x] Documenté dans [.github/deploiement.md](.github/deploiement.md) section "Tâches Cron" :
      `*/5 * * * * php bin/console messenger:consume async --time-limit=290 --limit=50 --no-debug`
- [ ] Configurer effectivement le cron dans cPanel (action manuelle sur le serveur)
- [ ] Vérifier l'absence de backlog après une journée : `messenger:stats`

**Note** : contradiction relevée entre [.claude/cicd.md](.claude/cicd.md) (mentionne un déploiement webhook auto) et [.claude/deploiement.md](.claude/deploiement.md) (indique que le webhook ne fonctionne pas sur o2switch, IPs Azure bloquées). À clarifier séparément.

---

## Backlog

1. Dév module suivi d'upload (état, progression %, métadonnées du fichier en cours)
2. Audit erreurs serveur/browser → vraie page d'erreur
3. Renforcer les échecs silencieux : lever exceptions explicites au lieu de `return null` (+ tests TDD)
