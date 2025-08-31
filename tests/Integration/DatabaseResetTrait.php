<?php

namespace App\Tests\Integration;

/**
 * Fournit un reset de base et fixtures pour les tests d’intégration.
 */
trait DatabaseResetTrait
{
    public static function resetDatabaseAndFixtures(): void
    {
        self::runShellCommand('php bin/console --env=test doctrine:database:create --if-not-exists');
        self::runShellCommand('php bin/console --env=test doctrine:schema:drop --force');
        self::runShellCommand('php bin/console --env=test doctrine:schema:create');
        self::runShellCommand('php bin/console --env=test hautelook:fixtures:load --no-interaction');
    }

    /**
     * Exécute une commande shell et lève une exception si elle échoue.
     */
    private static function runShellCommand(string $command): void
    {
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                sprintf(
                    "Command failed with exit code %d: %s\nOutput:\n%s",
                    $exitCode,
                    $command,
                    implode("\n", $output)
                )
            );
        }
    }
}
