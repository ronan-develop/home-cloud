<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\BroadcastTargetProvider;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN : expose la liste des instances déployées (#283), lue
 * depuis config/broadcast_targets.php — miroir maintenu manuellement de
 * .deploy-targets (fichier shell non lu par le PHP applicatif).
 */
final class BroadcastTargetProviderTest extends TestCase
{
    private function makeProvider(): BroadcastTargetProvider
    {
        return new BroadcastTargetProvider(\dirname(__DIR__, 3) . '/config/broadcast_targets.php');
    }

    public function testGetAllTargetsReturnsAllKnownInstances(): void
    {
        $targets = $this->makeProvider()->getAllTargets();

        $this->assertArrayHasKey('ronan', $targets);
        $this->assertSame('https://ronan.lenouvel.me', $targets['ronan']);
        $this->assertArrayHasKey('yannick', $targets);
    }

    public function testGetTargetReturnsUrlForKnownInstance(): void
    {
        $this->assertSame(
            'https://damien.lenouvel.me',
            $this->makeProvider()->getTarget('damien'),
        );
    }

    public function testGetTargetReturnsNullForUnknownInstance(): void
    {
        $this->assertNull($this->makeProvider()->getTarget('inexistant'));
    }
}
