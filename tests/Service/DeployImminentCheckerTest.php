<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DeployImminentChecker;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN : lecture du signal de préavis écrit par
 * bin/deploy-nightly.sh (#422 étape 3/3) — un simple fichier plat contenant
 * le timestamp Unix du début du préavis, sur le même modèle que
 * ActivityTracker (pas de DB, lisible depuis un script bash).
 */
final class DeployImminentCheckerTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/deploy-imminent-test-' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);
    }

    public function testReturnsNullWhenFileAbsent(): void
    {
        $checker = new DeployImminentChecker($this->filePath, warningSeconds: 600);

        self::assertNull($checker->getDeploymentEta());
    }

    public function testReturnsEtaWhenFilePresent(): void
    {
        file_put_contents($this->filePath, (string) time());
        $checker = new DeployImminentChecker($this->filePath, warningSeconds: 600);

        $eta = $checker->getDeploymentEta();

        self::assertNotNull($eta);
        self::assertEqualsWithDelta(time() + 600, $eta->getTimestamp(), 2);
    }

    public function testReturnsNullWhenFileContentIsInvalid(): void
    {
        file_put_contents($this->filePath, 'not-a-timestamp');
        $checker = new DeployImminentChecker($this->filePath, warningSeconds: 600);

        self::assertNull($checker->getDeploymentEta());
    }
}
