<?php
/**
 * Webhook de déploiement — déclenché par GitHub Actions après CI success.
 * Vérifie la signature HMAC-SHA256, puis exécute git pull + composer install.
 *
 * Secret à définir dans .env.local : DEPLOY_WEBHOOK_SECRET=<token>
 * Et dans les secrets GitHub : DEPLOY_WEBHOOK_SECRET=<même token>
 */

declare(strict_types=1);

// ── Sécurité : uniquement POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ── Chargement du secret depuis .env.local ────────────────────────────────────
$envFile = __DIR__ . '/../.env.local';
$secret = '';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        if (str_starts_with(trim($line), 'DEPLOY_WEBHOOK_SECRET=')) {
            $secret = trim(explode('=', $line, 2)[1]);
            break;
        }
    }
}

if (empty($secret)) {
    http_response_code(500);
    exit('Webhook secret not configured');
}

// ── Vérification de la signature HMAC-SHA256 ─────────────────────────────────
$payload    = file_get_contents('php://input');
$signature  = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected   = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// ── Vérification de la branche ───────────────────────────────────────────────
$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    exit('Skipped: not main branch');
}

// ── Déploiement ───────────────────────────────────────────────────────────────
$projectDir = dirname(__DIR__);
$logFile    = $projectDir . '/var/log/deploy.log';
$timestamp  = date('Y-m-d H:i:s');

$commands = [
    "cd {$projectDir} && git pull origin main 2>&1",
    "cd {$projectDir} && /opt/cpanel/composer/bin/composer install --no-interaction --prefer-dist --no-progress --no-dev 2>&1",
    "cd {$projectDir} && /usr/local/bin/php bin/console cache:clear --env=prod 2>&1",
    "cd {$projectDir} && /usr/local/bin/php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>&1",
];

$log = "[{$timestamp}] Déploiement déclenché\n";
$success = true;

foreach ($commands as $cmd) {
    $output = shell_exec($cmd);
    $log .= "[{$timestamp}] CMD: {$cmd}\n{$output}\n";
    if ($output === null) {
        $success = false;
        break;
    }
}

$log .= "[{$timestamp}] " . ($success ? "✅ Déploiement terminé\n" : "❌ Erreur lors du déploiement\n");
file_put_contents($logFile, $log, FILE_APPEND);

http_response_code($success ? 200 : 500);
echo $success ? 'Deployed' : 'Deployment failed';
