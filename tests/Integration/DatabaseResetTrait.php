<?php

namespace App\Tests\Integration;

/**
 * Fournit un reset de base et fixtures pour les tests d’intégration.
 */
trait DatabaseResetTrait
{
    public static function resetDatabaseAndFixtures(): void
    {
        shell_exec('php bin/console --env=test doctrine:database:create --if-not-exists');
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
    }
}
