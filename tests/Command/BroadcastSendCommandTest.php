<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BroadcastSendCommand;
use App\Interface\BroadcastOrchestratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * TDD RED → GREEN : déclenchement CLI du broadcast admin (#283) — utile
 * pour un essai manuel ou un futur cron, mais surtout la brique déjà
 * réutilisée par l'interface admin web.
 */
final class BroadcastSendCommandTest extends TestCase
{
    public function testDispatchesToAllInstancesByDefault(): void
    {
        $orchestrator = $this->createMock(BroadcastOrchestratorInterface::class);
        $orchestrator->expects($this->once())
            ->method('dispatch')
            ->with('Sujet', 'Corps', null, false)
            ->willReturn(['ronan' => true, 'yannick' => true]);

        $tester = $this->commandTester($orchestrator);
        $tester->execute(['--subject' => 'Sujet', '--body' => 'Corps']);

        $tester->assertCommandIsSuccessful();
    }

    public function testTargetsSingleInstanceWithOption(): void
    {
        $orchestrator = $this->createMock(BroadcastOrchestratorInterface::class);
        $orchestrator->expects($this->once())
            ->method('dispatch')
            ->with('Sujet', 'Corps', 'yannick', false)
            ->willReturn(['yannick' => true]);

        $tester = $this->commandTester($orchestrator);
        $tester->execute(['--subject' => 'Sujet', '--body' => 'Corps', '--instance' => 'yannick']);

        $tester->assertCommandIsSuccessful();
    }

    public function testDryRunOptionPreventsRealSend(): void
    {
        $orchestrator = $this->createMock(BroadcastOrchestratorInterface::class);
        $orchestrator->expects($this->once())
            ->method('dispatch')
            ->with('Sujet', 'Corps', null, true)
            ->willReturn(['ronan' => true]);

        $tester = $this->commandTester($orchestrator);
        $tester->execute(['--subject' => 'Sujet', '--body' => 'Corps', '--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
    }

    public function testReportsFailedInstancesInOutput(): void
    {
        $orchestrator = $this->createMock(BroadcastOrchestratorInterface::class);
        $orchestrator->method('dispatch')->willReturn(['ronan' => true, 'yannick' => false]);

        $tester = $this->commandTester($orchestrator);
        $tester->execute(['--subject' => 'Sujet', '--body' => 'Corps']);

        $this->assertStringContainsString('yannick', $tester->getDisplay());
    }

    private function commandTester(BroadcastOrchestratorInterface $orchestrator): CommandTester
    {
        $command = new BroadcastSendCommand($orchestrator);
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:broadcast:send'));
    }
}
