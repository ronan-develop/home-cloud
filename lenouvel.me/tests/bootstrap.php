<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    $dotenv = new Dotenv();
    $envFile = dirname(__DIR__) . '/.env';
    $dotenv->bootEnv($envFile);
    // Si on est en test, on supprime les variables JWT de l'environnement puis on surcharge avec .env.test
    if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'test') {
        foreach (
            [
                'JWT_SECRET_KEY',
                'JWT_PUBLIC_KEY',
                'JWT_PASSPHRASE',
            ] as $jwtVar
        ) {
            unset($_ENV[$jwtVar], $_SERVER[$jwtVar]);
        }
        $testEnvFile = dirname(__DIR__) . '/.env.test';
        if (file_exists($testEnvFile)) {
            $dotenv->overload($testEnvFile);
        }
        // Log temporaire pour debug
        fwrite(STDERR, "[BOOTSTRAP DEBUG] JWT_PASSPHRASE=" . ($_ENV['JWT_PASSPHRASE'] ?? 'vide') . "\n");
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
