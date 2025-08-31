<?php

namespace App\Tests\Integration;

/**
 * Fournit un reset de base et fixtures pour les tests d’intégration.
 *
 * \note
 * Par défaut, ce trait ne lance pas les migrations Doctrine mais recrée le schéma à partir des métadonnées.
 * Cela garantit un état propre et rapide pour les tests d’intégration.
 * Si vous souhaitez tester les migrations, remplacez les commandes doctrine:schema:* par doctrine:migrations:migrate
 * ou adaptez la méthode resetDatabaseAndFixtures() selon vos besoins.
 *
 * Exemple pour appliquer les migrations :
 *   self::runShellCommand('php bin/console --env=test doctrine:migrations:migrate --no-interaction');
 *
 * Voir la documentation projet pour plus de détails sur la stratégie de tests.
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
     *
     * @param string $command La commande shell à exécuter
     * @throws \RuntimeException Si la commande retourne un code de sortie non nul
     * @return void
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
