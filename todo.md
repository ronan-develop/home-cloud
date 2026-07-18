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

---

## Tickets GitHub — 2026-07-18

Tous suivis sur https://github.com/ronan-develop/home-cloud/issues

### 🔴 Critique

- [x] [#237](https://github.com/ronan-develop/home-cloud/issues/237) — BUG : suppression de fichiers/dossiers non scopée, perte de dossiers racine (fixé PR #249, déployé en prod)

### 🟠 Bugs / retours utilisateur (2026-07-18)

- [ ] [#238](https://github.com/ronan-develop/home-cloud/issues/238) — Glisser-déposer d'un dossier local (avec structure) vers un dossier cible
- [ ] [#239](https://github.com/ronan-develop/home-cloud/issues/239) — Barre de progression d'upload ne fonctionne pas
- [ ] [#240](https://github.com/ronan-develop/home-cloud/issues/240) — Téléchargement d'un dossier entier ne fonctionne pas
- [ ] [#241](https://github.com/ronan-develop/home-cloud/issues/241) — Prévisualisation des fichiers PDF

### 🟡 Backlog fonctionnel initial (001 à 006, 2026-07-18)

- [ ] [#242](https://github.com/ronan-develop/home-cloud/issues/242) — Renommer un album
- [ ] [#243](https://github.com/ronan-develop/home-cloud/issues/243) — Zoom sur une photo (clic ou +/-)
- [ ] [#244](https://github.com/ronan-develop/home-cloud/issues/244) — Nettoyer les liens publics à la révocation du partage
- [ ] [#245](https://github.com/ronan-develop/home-cloud/issues/245) — Curseur de contrôle de concurrence d'envoi à l'upload
- [ ] [#246](https://github.com/ronan-develop/home-cloud/issues/246) — Suppression photo « mes fichiers » : proposer conservation dans « mes albums »
- [ ] [#247](https://github.com/ronan-develop/home-cloud/issues/247) — Vérifier/fiabiliser le flux import et nouveau fichier

### 🟢 Perf/UX upload (2026-07-18, suite investigation #237)

- [ ] [#251](https://github.com/ronan-develop/home-cloud/issues/251) — Accélérer le traitement des uploads (exif_thumbnail) + statut « en cours » tant que le Media n'existe pas
