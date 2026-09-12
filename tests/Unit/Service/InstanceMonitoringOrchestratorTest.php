<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Interface\BroadcastTargetProviderInterface;
use App\Interface\InstanceMonitoringReporterInterface;
use App\Service\InstanceMonitoringOrchestrator;
use App\Service\InstanceMonitoringSnapshot;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * TDD RED → GREEN : orchestration multi-instances du monitoring admin
 * (#376), sur le modèle de BroadcastOrchestrator (#283). L'instance courante
 * est traitée en local (pas d'aller-retour réseau), les autres appelées sur
 * /internal/monitoring — une instance injoignable ne doit jamais bloquer
 * l'affichage des autres (résultat partiel, reachable: false).
 */
final class InstanceMonitoringOrchestratorTest extends TestCase
{
    private const TARGETS = [
        'ronan'   => 'https://ronan.lenouvel.me',
        'yannick' => 'https://yannick.lenouvel.me',
        'elea'    => 'https://elea.lenouvel.me',
    ];

    private function makeTargetProviderStub(): BroadcastTargetProviderInterface
    {
        $stub = $this->createStub(BroadcastTargetProviderInterface::class);
        $stub->method('getAllTargets')->willReturn(self::TARGETS);

        return $stub;
    }

    private function makeLocalReporterStub(): InstanceMonitoringReporterInterface
    {
        $stub = $this->createStub(InstanceMonitoringReporterInterface::class);
        $stub->method('getLocalSnapshot')->willReturn(
            new InstanceMonitoringSnapshot(reachable: true, userCount: 5, totalStorageBytes: 1000, gitRevision: 'abc123'),
        );

        return $stub;
    }

    private function makeOrchestrator(MockHttpClient $httpClient): InstanceMonitoringOrchestrator
    {
        return new InstanceMonitoringOrchestrator(
            $httpClient,
            $this->makeTargetProviderStub(),
            $this->makeLocalReporterStub(),
            $this->createStub(LoggerInterface::class),
            currentInstance: 'ronan',
            sharedToken: 'the-secret-token',
        );
    }

    public function testLocalInstanceHandledWithoutHttpCall(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function ($method, $url) use (&$requests) {
            $requests[] = $url;

            return new MockResponse('{"userCount":1,"totalStorageBytes":1,"gitRevision":"x"}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $snapshots = $orchestrator->collectAll();

        $this->assertCount(2, $requests, 'Seules les instances distantes doivent déclencher un appel HTTP.');
        $this->assertTrue($snapshots['ronan']->reachable);
        $this->assertSame(5, $snapshots['ronan']->userCount);
    }

    public function testRemoteInstanceSnapshotParsedFromJson(): void
    {
        $httpClient = new MockHttpClient(function ($method, $url) {
            return new MockResponse('{"userCount":42,"totalStorageBytes":999,"gitRevision":"deadbee"}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $snapshots = $orchestrator->collectAll();

        $this->assertTrue($snapshots['yannick']->reachable);
        $this->assertSame(42, $snapshots['yannick']->userCount);
        $this->assertSame(999, $snapshots['yannick']->totalStorageBytes);
        $this->assertSame('deadbee', $snapshots['yannick']->gitRevision);
    }

    public function testUnreachableInstanceDoesNotAbortOthers(): void
    {
        $httpClient = new MockHttpClient(function ($method, $url) {
            if (str_contains($url, 'yannick')) {
                throw new TransportException('unreachable');
            }

            return new MockResponse('{"userCount":1,"totalStorageBytes":1,"gitRevision":"x"}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $snapshots = $orchestrator->collectAll();

        $this->assertFalse($snapshots['yannick']->reachable);
        $this->assertTrue($snapshots['elea']->reachable);
    }

    public function testSendsSharedTokenHeaderOnEachRemoteCall(): void
    {
        $capturedOptions = [];
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
            $capturedOptions[] = $options;

            return new MockResponse('{"userCount":1,"totalStorageBytes":1,"gitRevision":"x"}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $orchestrator->collectAll();

        $this->assertContains('X-Broadcast-Token: the-secret-token', $capturedOptions[0]['headers']);
    }
}
