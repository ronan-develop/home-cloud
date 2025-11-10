<?php

namespace App\Tests\Integration;

/**
 * Trait pour reset la base et les fixtures avant chaque test fonctionnel (API Platform, E2E...)
 * Cf. .github/copilot-instructions.md
 */
trait DatabaseResetTrait
{
    public static function resetDatabaseAndFixtures(): void
    {
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        shell_exec('php bin/console --env=test doctrine:fixtures:load --no-interaction');
    }
}
