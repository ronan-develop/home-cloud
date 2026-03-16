# Déploiement — HomeCloud sur O2Switch

Guide de déploiement adapté à l'hébergement mutualisé cPanel O2Switch.

---

## Vue d'ensemble des scripts

Ces scripts ont des rôles et des emplacements distincts :

```
bin/deploy.sh          → lancé depuis ta machine locale
├── backup.sh          → cron O2Switch, quotidien (prérequis du rollback)
├── rollback.sh        → SSH manuel, en urgence
└── monitor.sh         → cron O2Switch, toutes les heures
```

| Script | Où il vit | Qui le déclenche | Rôle |
|---|---|---|---|
| `bin/deploy.sh` | Local | Toi, depuis ton terminal | Déploie le code sur le serveur via SSH |
| `scripts/backup.sh` | Serveur O2Switch | Cron (1h du matin) | Sauvegarde BDD + uploads + `.env` |
| `scripts/rollback.sh` | Serveur O2Switch | Toi, en SSH, en urgence | Restaure un backup si le site est cassé |
| `scripts/monitor.sh` | Serveur O2Switch | Cron (toutes les heures) | Alerte email si `/api/health` ne répond plus |

### `backup.sh` — Sauvegarde quotidienne

Prérequis indispensable du rollback. Sans backups quotidiens, aucune restauration possible.

**Ce qu'il fait :**

- `mysqldump` compressé en `.sql.gz`
- Archive de `public/uploads/` en `.tar.gz`
- Copie de `.env` pour la config
- Purge automatique des backups de plus de 7 jours

**Installation sur O2Switch :**

```bash
mkdir -p ~/scripts ~/backups ~/logs
# Copier le contenu du script (voir section "Tâches Cron" plus bas)
nano ~/scripts/backup.sh
chmod +x ~/scripts/backup.sh
```

**Déclenchement :**

```bash
# Manuel
~/scripts/backup.sh

# Automatique via cPanel > Cron Jobs
0 1 * * * /home/username/scripts/backup.sh >> /home/username/logs/cron.log 2>&1
```

**Vérification :**

```bash
ls -lh ~/backups/$(date +%Y-%m-%d)/
# Doit afficher : database.sql.gz, uploads.tar.gz, .env.backup
```

---

### `rollback.sh` — Restauration d'urgence

Lancé manuellement en SSH si un déploiement casse le site.

**Ce qu'il fait :**

1. Crée un backup d'urgence de l'état actuel
2. Active `var/maintenance.flag`
3. Écrase `~/symfony_app` par le backup choisi
4. Rejoue le dump SQL dans la base
5. `cache:clear` + permissions
6. Vérifie `/api/health` avant de rouvrir le site

**Installation sur O2Switch :**

```bash
nano ~/scripts/rollback.sh
chmod +x ~/scripts/rollback.sh
```

**Utilisation :**

```bash
ssh username@ssh.o2switch.net

# Lister les backups disponibles
~/scripts/rollback.sh

# Rollback vers une date précise
~/scripts/rollback.sh 2024-01-15

# Suivre la progression
tail -f ~/logs/rollback.log
```

**Dépendance :** nécessite que `backup.sh` tourne en cron — sans backup, pas de rollback.

---

### `monitor.sh` — Health check horaire

Seul moyen d'être notifié d'une panne sur O2Switch (pas de Datadog, pas d'APM).

**Ce qu'il fait :**

- `curl /api/health` toutes les heures via cron
- Si HTTP ≠ 200 → email d'alerte immédiat

**Installation sur O2Switch :**

```bash
nano ~/scripts/monitor.sh
chmod +x ~/scripts/monitor.sh
```

**Déclenchement :**

```bash
# Manuel
~/scripts/monitor.sh

# Automatique via cPanel > Cron Jobs
0 * * * * /home/username/scripts/monitor.sh
```

**Vérification que les alertes partent :**

```bash
# Simuler une panne (endpoint inexistant)
HEALTH_URL="https://votre-domaine.com/api/inexistant" ~/scripts/monitor.sh
# → doit envoyer un email
```

**Note :** la commande `mail` doit être disponible sur le serveur. Vérifier avec `which mail` en SSH. Si absente, remplacer par un appel `curl` vers une API d'envoi d'email (ex. Mailgun, Brevo).

---

## Scripts de déploiement

### `scripts/deploy.sh`

```bash
#!/bin/bash
# Script de déploiement pour O2Switch/cPanel

set -e

APP_DIR="/home/username/symfony_app"
BACKUP_DIR="/home/username/backups"
LOG_FILE="/home/username/logs/deploy.log"
DATE=$(date +%Y-%m-%d_%H-%M-%S)

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

backup() {
    log "Création du backup..."
    BACKUP_PATH="$BACKUP_DIR/$DATE"
    mkdir -p "$BACKUP_PATH"
    cp -r "$APP_DIR" "$BACKUP_PATH/app"
    mysqldump -u username -p'password' database_name > "$BACKUP_PATH/database.sql"
    log "Backup créé : $BACKUP_PATH"
}

deploy() {
    log "Début du déploiement..."
    cd "$APP_DIR"

    log "Activation du mode maintenance..."
    touch var/maintenance.flag

    log "Récupération du code..."
    git fetch origin
    git checkout main
    git pull origin main

    log "Installation des dépendances..."
    composer install --no-dev --optimize-autoloader --no-interaction

    log "Exécution des migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction

    log "Nettoyage du cache..."
    php bin/console cache:clear --env=prod --no-debug
    php bin/console cache:warmup --env=prod --no-debug

    if [ -f "package.json" ]; then
        log "Compilation des assets..."
        npm install
        npm run build
    fi

    log "Ajustement des permissions..."
    chmod -R 755 var/cache var/log var/sessions

    log "Désactivation du mode maintenance..."
    rm -f var/maintenance.flag

    log "Déploiement terminé avec succès !"
}

rollback() {
    BACKUP_PATH=$1
    if [ -z "$BACKUP_PATH" ]; then
        log "Erreur : chemin de backup requis"
        exit 1
    fi

    log "Rollback vers $BACKUP_PATH..."
    rm -rf "$APP_DIR"
    cp -r "$BACKUP_PATH/app" "$APP_DIR"
    mysql -u username -p'password' database_name < "$BACKUP_PATH/database.sql"

    cd "$APP_DIR"
    php bin/console cache:clear --env=prod
    log "Rollback terminé"
}

case "$1" in
    backup)   backup ;;
    deploy)   backup && deploy ;;
    rollback) rollback "$2" ;;
    *)
        echo "Usage: $0 {backup|deploy|rollback <backup_path>}"
        exit 1
        ;;
esac
```

### `scripts/test.sh`

```bash
#!/bin/bash
set -e

APP_DIR="/home/username/symfony_app"
LOG_FILE="/home/username/logs/test.log"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

cd "$APP_DIR"
log "Début des tests..."

log "Tests unitaires..."
php bin/phpunit tests/Unit --testdox

log "Tests d'intégration..."
php bin/phpunit tests/Integration --testdox

log "Tests fonctionnels..."
php bin/phpunit tests/Functional --testdox

if [ -f "vendor/bin/phpstan" ]; then
    log "Analyse statique (PHPStan)..."
    vendor/bin/phpstan analyse src tests --level=8
fi

if [ -f "vendor/bin/php-cs-fixer" ]; then
    log "Vérification du style de code..."
    vendor/bin/php-cs-fixer fix --dry-run --diff
fi

log "Vérification de sécurité..."
symfony security:check || true

log "Tous les tests sont passés !"
```

### Rendre les scripts exécutables

```bash
chmod +x scripts/deploy.sh
chmod +x scripts/test.sh
```

---

## Workflow Git adapté O2Switch

### Configuration Git sur le serveur

```bash
ssh username@ssh.o2switch.net

git config --global user.name "Votre Nom"
git config --global user.email "votre@email.com"

cd ~
git clone https://github.com/votre-org/votre-projet.git symfony_app
cd symfony_app

git checkout -b production
git push -u origin production
```

### Workflow de déploiement

```mermaid
graph LR
    A[Dev local] -->|git push| B[GitHub main]
    B -->|Merge vers production| C[GitHub production]
    C -->|SSH + git pull| D[O2Switch]
    D -->|scripts/deploy.sh| E[Production live]
    E -->|Si problème| F[scripts/rollback.sh]
    F --> G[Version précédente]
```

### Procédure de déploiement

```bash
# 1. En local — tester et merger
git checkout main && git pull origin main
./scripts/test.sh
git checkout production
git merge main
git push origin production

# 2. Sur O2Switch
ssh username@ssh.o2switch.net
cd ~/symfony_app
./scripts/deploy.sh deploy

# 3. Vérifier
curl https://votre-domaine.com/api/health
tail -f ~/logs/deploy.log

# 4. Si problème
./scripts/deploy.sh rollback ~/backups/2024-01-15_14-30-00
```

---

## Adaptations O2Switch par ticket de refactoring

### Vague 1 — Quick wins

Les tickets `qw-filename-validator`, `qw-auth-resolver` et `ct-iri-helper` ne
nécessitent aucune adaptation spécifique à O2Switch.

**Tests sur le serveur :**

```bash
ssh username@ssh.o2switch.net
cd ~/symfony_app
php bin/phpunit tests/Unit/Service/FilenameValidatorTest.php
php bin/phpunit tests/Unit/Service/IriExtractorTest.php
tail -f var/log/prod.log
```

### Vague 2 — `ct-repo-interfaces` (cache filesystem)

```php
// src/Repository/FolderRepository.php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class FolderRepository extends ServiceEntityRepository implements FolderRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheInterface $cache
    ) {
        parent::__construct($registry, Folder::class);
    }

    public function findByOwner(User $user): array
    {
        return $this->cache->get(
            "folders_owner_{$user->getId()}",
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(3600);
                $item->tag(['folders', "user_{$user->getId()}"]);

                return $this->createQueryBuilder('f')
                    ->where('f.owner = :owner')
                    ->setParameter('owner', $user)
                    ->getQuery()
                    ->getResult();
            }
        );
    }
}
```

```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.filesystem
        pools:
            cache.folders:
                adapter: cache.adapter.filesystem
                default_lifetime: 3600
            cache.albums:
                adapter: cache.adapter.filesystem
                default_lifetime: 3600
```

### Vague 2 — `qw-auth-checker` (logging de sécurité)

```php
// src/Security/OwnershipChecker.php
final readonly class OwnershipChecker
{
    public function __construct(
        private AuthenticationResolver $authResolver,
        private LoggerInterface $securityLogger
    ) {}

    public function denyUnlessOwner(Folder|Album|Share $resource): void
    {
        $user = $this->authResolver->getAuthenticatedUser();

        if (!$resource->getOwner()->equals($user)) {
            $this->securityLogger->warning('Unauthorized access attempt', [
                'user_id'       => $user->getId(),
                'user_email'    => $user->getEmail(),
                'resource_type' => $resource::class,
                'resource_id'   => $resource->getId(),
                'owner_id'      => $resource->getOwner()->getId(),
                'ip'            => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp'     => time(),
            ]);

            throw new AccessDeniedException('You are not the owner of this resource');
        }
    }
}
```

**Analyse des logs de sécurité :**

```bash
# ~/scripts/analyze_security.sh
#!/bin/bash
LOG_FILE="/home/username/symfony_app/var/log/security.log"

echo "=== Tentatives d'accès non autorisées (dernières 24h) ==="
grep "Unauthorized access attempt" "$LOG_FILE" \
    | jq -r '[.user_email, .resource_type, .ip] | @tsv' \
    | sort | uniq -c | sort -rn

echo ""
echo "=== IPs suspectes (> 5 tentatives) ==="
grep "Unauthorized access attempt" "$LOG_FILE" \
    | jq -r '.ip' \
    | sort | uniq -c | sort -rn \
    | awk '$1 > 5 {print $2 " : " $1 " tentatives"}'
```

### Vague 3 — `mt-folder-processor-srp` (métriques sans Stopwatch)

```php
// src/Service/FolderService.php
final readonly class FolderService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FilenameValidator $filenameValidator,
        private OwnershipChecker $ownershipChecker,
        private LoggerInterface $logger
    ) {}

    public function create(User $owner, array $data): Folder
    {
        $startTime = microtime(true);

        $this->filenameValidator->validate($data['name'], 'folder');

        $folder = new Folder();
        $folder->setName($data['name']);
        $folder->setOwner($owner);

        if (isset($data['parent'])) {
            $parent = $this->folderRepository->find($data['parent']);
            $this->ownershipChecker->denyUnlessOwner($parent);
            $folder->setParent($parent);
        }

        $this->folderRepository->save($folder);

        $this->logger->info('Folder created', [
            'folder_id'   => $folder->getId(),
            'owner_id'    => $owner->getId(),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'memory_mb'   => round(memory_get_usage(true) / 1024 / 1024, 2),
            'context'     => 'folder_service',
        ]);

        return $folder;
    }
}
```

**Analyse des performances :**

```bash
# ~/scripts/analyze_performance.sh
#!/bin/bash
LOG_FILE="/home/username/symfony_app/var/log/prod.log"

echo "=== Performances moyennes ==="@solid
echo ""

echo "Création de dossiers :"
grep "Folder created" "$LOG_FILE" \
    | jq -r '.duration_ms' \
    | awk '{sum+=$1; count++} END {printf "  Moyenne: %.2f ms\n", sum/count}'

echo "Mise à jour de dossiers :"
grep "Folder updated" "$LOG_FILE" \
    | jq -r '.duration_ms' \
    | awk '{sum+=$1; count++} END {printf "  Moyenne: %.2f ms\n", sum/count}'

echo ""
echo "=== Opérations lentes (> 500ms) ==="
grep -E "Folder (created|updated|deleted)" "$LOG_FILE" \
    | jq 'select(.duration_ms > 500) | {operation: .message, duration: .duration_ms}'
```

---

## Tâches Cron (remplacement de Supervisor)

```bash
# Via cPanel > Cron Jobs

# Nettoyage du cache (tous les jours à 3h)
0 3 * * * cd /home/username/symfony_app && php bin/console cache:pool:clear cache.folders cache.albums >> /home/username/logs/cron.log 2>&1

# Rotation des logs (tous les lundis à 4h)
0 4 * * 1 find /home/username/symfony_app/var/log -name "*.log" -mtime +30 -delete >> /home/username/logs/cron.log 2>&1

# Nettoyage des sessions (tous les jours à 2h)
0 2 * * * find /home/username/symfony_app/var/sessions -type f -mtime +7 -delete >> /home/username/logs/cron.log 2>&1

# Backup automatique (tous les jours à 1h)
0 1 * * * /home/username/scripts/backup.sh >> /home/username/logs/cron.log 2>&1

# Analyse de sécurité (tous les jours à 5h)
0 5 * * * /home/username/scripts/analyze_security.sh >> /home/username/logs/security_report.log 2>&1

# Health check (toutes les heures)
0 * * * * curl -s https://votre-domaine.com/api/health >> /home/username/logs/health.log 2>&1

# Rapport hebdomadaire (tous les lundis à 8h)
0 8 * * 1 /home/username/scripts/weekly_report.sh

# Blocage IPs suspectes (tous les jours à 6h)
0 6 * * * /home/username/scripts/block_suspicious_ips.sh >> /home/username/logs/security_block.log 2>&1
```

### `scripts/backup.sh`

```bash
#!/bin/bash
set -e

BACKUP_DIR="/home/username/backups"
APP_DIR="/home/username/symfony_app"
DATE=$(date +%Y-%m-%d)
RETENTION_DAYS=7

mkdir -p "$BACKUP_DIR/$DATE"

mysqldump -u username -p'password' database_name | gzip > "$BACKUP_DIR/$DATE/database.sql.gz"

if [ -d "$APP_DIR/public/uploads" ]; then
    tar -czf "$BACKUP_DIR/$DATE/uploads.tar.gz" -C "$APP_DIR/public" uploads
fi

cp "$APP_DIR/.env" "$BACKUP_DIR/$DATE/.env.backup"

find "$BACKUP_DIR" -type d -mtime +$RETENTION_DAYS -exec rm -rf {} +

echo "[$(date)] Backup terminé : $BACKUP_DIR/$DATE"
```

---

## Monitoring adapté O2Switch

### Endpoint de santé

```php
// src/Controller/HealthController.php
#[Route('/api/health', name: 'api_health', methods: ['GET'])]
public function health(): JsonResponse
{
    $checks = [
        'database'   => $this->checkDatabase(),
        'filesystem' => $this->checkFilesystem(),
        'cache'      => $this->checkCache(),
    ];

    $status = in_array(false, $checks, true) ? 'unhealthy' : 'healthy';

    return new JsonResponse([
        'status'    => $status,
        'checks'    => $checks,
        'timestamp' => time(),
    ], $status === 'healthy' ? 200 : 503);
}
```

### `scripts/monitor.sh`

```bash
#!/bin/bash
HEALTH_URL="https://votre-domaine.com/api/health"
LOG_FILE="/home/username/logs/monitor.log"
ALERT_EMAIL="admin@example.com"

RESPONSE=$(curl -s -w "\n%{http_code}" "$HEALTH_URL")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

echo "[$(date)] HTTP $HTTP_CODE - $BODY" >> "$LOG_FILE"

if [ "$HTTP_CODE" != "200" ]; then
    echo "Application unhealthy: $BODY" | \
        mail -s "[ALERT] Application Down" "$ALERT_EMAIL"
fi
```

### `scripts/weekly_report.sh`

```bash
#!/bin/bash
REPORT_FILE="/home/username/reports/weekly_$(date +%Y-%m-%d).txt"
LOG_DIR="/home/username/symfony_app/var/log"

mkdir -p /home/username/reports

{
    echo "================================================"
    echo "Rapport hebdomadaire — $(date)"
    echo "================================================"

    echo ""
    echo "=== Statistiques d'utilisation ==="
    echo "Dossiers créés : $(grep "Folder created" "$LOG_DIR/prod.log" | wc -l)"
    echo "Albums créés   : $(grep "Album created" "$LOG_DIR/prod.log" | wc -l)"

    echo ""
    echo "=== Performances ==="
    echo "Temps moyen création dossier :"
    grep "Folder created" "$LOG_DIR/prod.log" \
        | jq -r '.duration_ms' \
        | awk '{sum+=$1; count++} END {printf "  %.2f ms\n", sum/count}'

    echo ""
    echo "=== Sécurité ==="
    echo "Tentatives non autorisées : $(grep "Unauthorized access attempt" "$LOG_DIR/security.log" | wc -l)"
    echo "IPs uniques suspectes     : $(grep "Unauthorized access attempt" "$LOG_DIR/security.log" | jq -r '.ip' | sort -u | wc -l)"

    echo ""
    echo "=== Erreurs ==="
    echo "CRITICAL : $(grep -c "CRITICAL" "$LOG_DIR/prod.log" || echo 0)"
    echo "ERROR    : $(grep -c "ERROR" "$LOG_DIR/prod.log" || echo 0)"
    echo "WARNING  : $(grep -c "WARNING" "$LOG_DIR/prod.log" || echo 0)"

    echo ""
    echo "=== Espace disque ==="
    du -sh "$LOG_DIR" "$LOG_DIR/../cache" "$LOG_DIR/../sessions"

} > "$REPORT_FILE"

mail -s "Rapport hebdomadaire — $(date +%Y-%m-%d)" admin@example.com < "$REPORT_FILE"
echo "Rapport généré : $REPORT_FILE"
```

---

## Sécurité renforcée

### Protection `.env` via `.htaccess`

```apache
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>
```

### `scripts/secure.sh`

```bash
#!/bin/bash
APP_DIR="/home/username/symfony_app"
cd "$APP_DIR"

find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

chmod 755 bin/console bin/phpunit
chmod 600 .env .env.local
chmod -R 775 var/cache var/log var/sessions

echo "Permissions sécurisées appliquées"
```

### `scripts/block_suspicious_ips.sh`

```bash
#!/bin/bash
LOG_FILE="/home/username/symfony_app/var/log/security.log"
HTACCESS="/home/username/public_html/.htaccess"
THRESHOLD=10

SUSPICIOUS_IPS=$(grep "Unauthorized access attempt" "$LOG_FILE" \
    | jq -r '.ip' \
    | sort | uniq -c \
    | awk -v t="$THRESHOLD" '$1 > t {print $2}')

cp "$HTACCESS" "$HTACCESS.backup"

echo "" >> "$HTACCESS"
echo "# Auto-generated IP blocks — $(date)" >> "$HTACCESS"

for IP in $SUSPICIOUS_IPS; do
    if ! grep -q "Require not ip $IP" "$HTACCESS"; then
        echo "Require not ip $IP" >> "$HTACCESS"
        echo "Blocked: $IP"
    fi
done
```

---

## Rollback avancé

### `scripts/rollback.sh`

```bash
#!/bin/bash
set -e

BACKUP_DIR="/home/username/backups"
APP_DIR="/home/username/symfony_app"
LOG_FILE="/home/username/logs/rollback.log"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

if [ -z "$1" ]; then
    echo "Usage: $0 <backup_date>"
    echo "Backups disponibles :"
    ls -1 "$BACKUP_DIR"
    exit 1
fi

BACKUP_PATH="$BACKUP_DIR/$1"
[ ! -d "$BACKUP_PATH" ] && log "Backup introuvable : $BACKUP_PATH" && exit 1

log "Début du rollback vers $1..."

EMERGENCY="$BACKUP_DIR/emergency_$(date +%Y-%m-%d_%H-%M-%S)"
log "Backup d'urgence : $EMERGENCY"
mkdir -p "$EMERGENCY"
cp -r "$APP_DIR" "$EMERGENCY/app"
mysqldump -u username -p'password' database_name > "$EMERGENCY/database.sql"

log "Activation mode maintenance..."
touch "$APP_DIR/var/maintenance.flag"

log "Restauration du code..."
rm -rf "$APP_DIR"
cp -r "$BACKUP_PATH/app" "$APP_DIR"

log "Restauration de la base de données..."
if [ -f "$BACKUP_PATH/database.sql.gz" ]; then
    gunzip < "$BACKUP_PATH/database.sql.gz" | mysql -u username -p'password' database_name
elif [ -f "$BACKUP_PATH/database.sql" ]; then
    mysql -u username -p'password' database_name < "$BACKUP_PATH/database.sql"
fi

[ -f "$BACKUP_PATH/.env.backup" ] && cp "$BACKUP_PATH/.env.backup" "$APP_DIR/.env"

[ -f "$BACKUP_PATH/uploads.tar.gz" ] && tar -xzf "$BACKUP_PATH/uploads.tar.gz" -C "$APP_DIR/public"

cd "$APP_DIR"
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug
chmod -R 755 var/cache var/log var/sessions && chmod 600 .env

rm -f var/maintenance.flag

STATUS=$(curl -s https://votre-domaine.com/api/health | jq -r '.status')
if [ "$STATUS" = "healthy" ]; then
    log "Rollback terminé avec succès !"
else
    log "Problème après rollback — backup d'urgence : $EMERGENCY"
    exit 1
fi
```

**Utilisation :**

```bash
# Lister les backups disponibles
./scripts/rollback.sh

# Rollback vers un backup spécifique
./scripts/rollback.sh 2024-01-15
```

---

## Tests sur staging

### Configuration staging

```bash
# Créer un sous-domaine via cPanel > Domains
# staging.votre-domaine.com → /home/username/symfony_app_staging
```

```dotenv
# ~/symfony_app_staging/.env.local
APP_ENV=staging
APP_DEBUG=false
DATABASE_URL="mysql://user:pass@localhost:3306/database_staging"
FILENAME_PATTERN='/^[a-zA-Z0-9_\-\.]+$/'
```

### `scripts/test_staging.sh`

```bash
#!/bin/bash
set -e

STAGING_URL="https://staging.votre-domaine.com"
LOG_FILE="/home/username/logs/staging_test.log"

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

log "Tests sur staging..."

# Health check
HEALTH=$(curl -s "$STAGING_URL/api/health")
[ "$(echo "$HEALTH" | jq -r '.status')" != "healthy" ] \
    && log "Health check échoué : $HEALTH" && exit 1
log "Health check OK"

# Création de dossier
RESPONSE=$(curl -s -X POST "$STAGING_URL/api/folders" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $STAGING_TOKEN" \
    -d '{"name":"test-staging-folder"}')
FOLDER_ID=$(echo "$RESPONSE" | jq -r '.id')
[ -z "$FOLDER_ID" ] || [ "$FOLDER_ID" = "null" ] \
    && log "Création échouée : $RESPONSE" && exit 1
log "Dossier créé : $FOLDER_ID"

# Mise à jour
UPDATED=$(curl -s -X PATCH "$STAGING_URL/api/folders/$FOLDER_ID" \
    -H "Content-Type: application/merge-patch+json" \
    -H "Authorization: Bearer $STAGING_TOKEN" \
    -d '{"name":"test-staging-updated"}')
[ "$(echo "$UPDATED" | jq -r '.name')" != "test-staging-updated" ] \
    && log "Mise à jour échouée : $UPDATED" && exit 1
log "Mise à jour OK"

# Suppression
curl -s -X DELETE "$STAGING_URL/api/folders/$FOLDER_ID" \
    -H "Authorization: Bearer $STAGING_TOKEN"
NOT_FOUND=$(curl -s -o /dev/null -w "%{http_code}" \
    "$STAGING_URL/api/folders/$FOLDER_ID" \
    -H "Authorization: Bearer $STAGING_TOKEN")
[ "$NOT_FOUND" != "404" ] && log "Suppression échouée" && exit 1
log "Suppression OK"

# Sécurité — requête non authentifiée
UNAUTHORIZED=$(curl -s -o /dev/null -w "%{http_code}" \
    "$STAGING_URL/api/folders" \
    -H "Content-Type: application/json" \
    -d '{"name":"unauthorized-test"}')
[ "$UNAUTHORIZED" != "401" ] \
    && log "Test sécurité échoué (attendu 401, reçu $UNAUTHORIZED)" && exit 1
log "Sécurité OK"

log "Tous les tests staging sont passés !"
```

---

## Guide de démarrage rapide

### Prérequis

- Accès SSH activé (cPanel > SSH Access)
- PHP 8.3 sélectionné (MultiPHP Manager)
- Git et Composer disponibles

### Installation initiale

```bash
# 1. Connexion SSH
ssh username@ssh.o2switch.net

# 2. Cloner le projet
cd ~
git clone https://github.com/votre-org/votre-projet.git symfony_app
cd symfony_app

# 3. Configuration
cp .env .env.local
nano .env.local
```

```dotenv
# .env.local
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=votre_secret_genere
DATABASE_URL="mysql://db_user:db_password@localhost:3306/db_name?serverVersion=8.0"
FILENAME_PATTERN='/^[a-zA-Z0-9_\-\.]+$/'
```

```bash
# 4. Dépendances
composer install --no-dev --optimize-autoloader

# 5. Migrations (base créée au préalable via cPanel > MySQL Databases)
php bin/console doctrine:migrations:migrate --no-interaction

# 6. Permissions
chmod -R 755 var/cache var/log var/sessions
chmod 600 .env .env.local

# 7. Vérification
curl https://votre-domaine.com/api/health
```

**Document root** (cPanel > Domains) : `/home/username/symfony_app/public`

### Opérations courantes

```bash
# Déployer une mise à jour
./scripts/deploy.sh deploy

# Voir les logs applicatifs
tail -f var/log/prod.log

# Voir les logs de sécurité
tail -f var/log/security.log

# Nettoyer le cache
php bin/console cache:clear --env=prod

# Créer un backup manuel
./scripts/deploy.sh backup

# Rollback
./scripts/rollback.sh 2024-01-15
```

### Dépannage

**Erreur 500 :**

```bash
tail -50 var/log/prod.log
ls -la var/
rm -rf var/cache/*
php bin/console cache:warmup --env=prod
```

**Base de données inaccessible :**

```bash
php bin/console dbal:run-sql "SELECT 1"
# Vérifier les credentials dans .env.local
# Redémarrer MySQL via cPanel si nécessaire
```

**Erreur de permissions :**

```bash
./scripts/secure.sh
```

---

## Checklist de déploiement

### Pré-déploiement (en local)

- [ ] Tests locaux passés (`./scripts/test.sh`)
- [ ] Code mergé sur la branche `production`
- [ ] `.env.prod` préparé (sans secrets)
- [ ] Documentation mise à jour

### Déploiement (sur O2Switch)

- [ ] Backup créé (`./scripts/deploy.sh backup`)
- [ ] Mode maintenance activé
- [ ] `git pull` effectué
- [ ] `composer install --no-dev` exécuté
- [ ] Migrations exécutées
- [ ] Cache vidé et réchauffé
- [ ] Permissions vérifiées (`./scripts/secure.sh`)
- [ ] Mode maintenance désactivé
- [ ] Health check OK (`curl /api/health`)

### Post-déploiement

- [ ] Tests fonctionnels manuels
- [ ] Logs vérifiés (`tail -f var/log/prod.log`)
- [ ] Monitoring 1h
- [ ] `.github/avancement.md` mis à jour

---

## Checklist infrastructure O2Switch

### Serveur

- [ ] PHP 8.3 configuré via MultiPHP Manager
- [ ] Document root pointant vers `symfony_app/public`
- [ ] `.htaccess` configuré (blocage `.env`, règles Symfony)
- [ ] Permissions correctes (755/644/600)
- [ ] SSH fonctionnel, Git et Composer installés

### Base de données

- [ ] Base MySQL créée via cPanel
- [ ] `DATABASE_URL` configuré dans `.env.local`
- [ ] Migrations exécutées
- [ ] Backup automatique configuré (cron)

### Cache et sessions

- [ ] Cache filesystem configuré
- [ ] `var/cache` et `var/sessions` créés avec permissions 755
- [ ] Nettoyage automatique configuré (cron)

### Logs

- [ ] Monolog configuré (rotating files)
- [ ] Canaux séparés (`security`, `prod`)
- [ ] Logs JSON structurés
- [ ] Rotation automatique (30 jours)

### Sécurité

- [ ] `.env.local` avec permissions 600
- [ ] Secrets externalisés (hors du dépôt)
- [ ] `FilenameValidator` actif
- [ ] `OwnershipChecker` actif
- [ ] Logs de sécurité activés
- [ ] Script de blocage IPs configuré (cron)

### Monitoring

- [ ] Endpoint `/api/health` fonctionnel
- [ ] Script `monitor.sh` configuré (cron)
- [ ] Alertes email configurées
- [ ] Rapport hebdomadaire automatisé

---

## Gestion des incidents

### P1 — Application inaccessible

**Diagnostic (5 min) :**

```bash
ssh username@ssh.o2switch.net
curl https://votre-domaine.com/api/health
tail -50 ~/symfony_app/var/log/prod.log
df -h
```

**Actions immédiates :**

```bash
# Erreur 500 → clear cache
cd ~/symfony_app
rm -rf var/cache/*
php bin/console cache:warmup --env=prod
./scripts/secure.sh

# Espace disque plein
find ~/symfony_app/var/log -name "*.log" -mtime +7 -delete
rm -rf ~/symfony_app/var/cache/*
find ~/symfony_app/var/sessions -type f -mtime +7 -delete
```

**Rollback (10 min) :**

```bash
ls -lt ~/backups | head -5
./scripts/rollback.sh 2024-01-XX
```

**Post-mortem :**

Créer `docs/incidents/YYYY-MM-DD-incident.md` :

```markdown
# Incident YYYY-MM-DD

## Résumé

[Description courte]

## Timeline

- HH:MM : Incident détecté
- HH:MM : Diagnostic effectué
- HH:MM : Action corrective appliquée
- HH:MM : Service restauré

## Cause racine

[Explication détaillée]

## Actions correctives

- [ ] ...

## Prévention future

- [ ] ...
```

---

## Support O2Switch

| Canal | Détail |
|-------|--------|
| Email | support@o2switch.fr |
| Téléphone | 04 44 44 60 40 |
| Espace client | <https://www.o2switch.fr/espace-client/> |
| Horaires | Lun–Ven 9h–12h30 / 14h–18h · Sam 9h–12h |

**Informations à fournir lors d'un ticket :**

- Nom de domaine
- Nom d'utilisateur cPanel
- Description du problème
- Extraits de logs (`var/log/prod.log`)
- Actions déjà tentées
- Niveau d'urgence (P1 / P2 / P3)

---

## Ressources

- [Tutoriels O2Switch](https://www.o2switch.fr/tutoriels/)
- [MultiPHP Manager](https://www.o2switch.fr/tutoriels/cpanel/selecteur-php/)
- [Cron Jobs cPanel](https://www.o2switch.fr/tutoriels/cpanel/taches-cron/)
- [Accès SSH](https://www.o2switch.fr/tutoriels/cpanel/acces-ssh/)
- [Déploiement Symfony](https://symfony.com/doc/current/deployment.html)
- [Performance Symfony](https://symfony.com/doc/current/performance.html)
