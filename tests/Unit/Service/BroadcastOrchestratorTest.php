<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Interface\BroadcastMailerInterface;
use App\Interface\BroadcastTargetProviderInterface;
use App\Service\BroadcastOrchestrator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * TDD RED → GREEN : orchestration multi-instances du broadcast admin (#283).
 * Chaque instance a sa propre DB — l'instance courante traite son cas en
 * local (via BroadcastMailerInterface), les autres sont appelées en HTTP sur
 * leur endpoint interne, protégé par un secret partagé.
 */
final class BroadcastOrchestratorTest extends TestCase
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
        $stub->method('getTarget')->willReturnCallback(
            fn (string $instance) => self::TARGETS[$instance] ?? null,
        );

        return $stub;
    }

    private function makeOrchestrator(
        MockHttpClient $httpClient,
        ?BroadcastMailerInterface $localMailer = null,
    ): BroadcastOrchestrator {
        $localMailer ??= $this->createStub(BroadcastMailerInterface::class);

        return new BroadcastOrchestrator(
            $httpClient,
            $this->makeTargetProviderStub(),
            $localMailer,
            $this->createStub(LoggerInterface::class),
            currentInstance: 'ronan',
            sharedToken: 'the-secret-token',
        );
    }

    public function testDispatchesToAllInstancesWhenNoTargetGiven(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$requests) {
            $requests[] = $url;

            return new MockResponse('{"sent":1}');
        });

        $localMailer = $this->createMock(BroadcastMailerInterface::class);
        $localMailer->expects($this->once())->method('sendToAllUsers')->willReturn(1);

        $orchestrator = $this->makeOrchestrator($httpClient, $localMailer);
        $orchestrator->dispatch('Sujet', 'Corps', targetInstance: null, dryRun: false);

        $this->assertCount(2, $requests);
        $this->assertContains('https://yannick.lenouvel.me/internal/broadcast', $requests);
        $this->assertContains('https://elea.lenouvel.me/internal/broadcast', $requests);
    }

    public function testDispatchesToSingleTargetedInstanceOnly(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$requests) {
            $requests[] = $url;

            return new MockResponse('{"sent":1}');
        });

        $localMailer = $this->createMock(BroadcastMailerInterface::class);
        $localMailer->expects($this->never())->method('sendToAllUsers');

        $orchestrator = $this->makeOrchestrator($httpClient, $localMailer);
        $orchestrator->dispatch('Sujet', 'Corps', targetInstance: 'yannick', dryRun: false);

        $this->assertSame(['https://yannick.lenouvel.me/internal/broadcast'], $requests);
    }

    public function testDryRunMakesNoHttpCallAtAll(): void
    {
        $called = false;
        $httpClient = new MockHttpClient(function () use (&$called) {
            $called = true;

            return new MockResponse('{"sent":0}');
        });

        $localMailer = $this->createMock(BroadcastMailerInterface::class);
        $localMailer->expects($this->once())->method('sendToAllUsers')->with('Sujet', 'Corps', true)->willReturn(0);

        $orchestrator = $this->makeOrchestrator($httpClient, $localMailer);
        $orchestrator->dispatch('Sujet', 'Corps', targetInstance: null, dryRun: true);

        $this->assertFalse($called, 'Aucun appel HTTP ne doit être fait en dry-run.');
    }

    public function testHttpFailureOnOneInstanceDoesNotAbortOthers(): void
    {
        $httpClient = new MockHttpClient(function ($method, $url) {
            if (str_contains($url, 'yannick')) {
                throw new TransportException('unreachable');
            }

            return new MockResponse('{"sent":1}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $result = $orchestrator->dispatch('Sujet', 'Corps', targetInstance: null, dryRun: false);

        $this->assertFalse($result['yannick']);
        $this->assertTrue($result['elea']);
    }

    public function testSendsSharedTokenHeaderOnEachCall(): void
    {
        $capturedOptions = [];
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedOptions) {
            $capturedOptions[] = $options;

            return new MockResponse('{"sent":1}');
        });

        $orchestrator = $this->makeOrchestrator($httpClient);
        $orchestrator->dispatch('Sujet', 'Corps', targetInstance: 'yannick', dryRun: false);

        $this->assertContains('X-Broadcast-Token: the-secret-token', $capturedOptions[0]['headers']);
    }
}
