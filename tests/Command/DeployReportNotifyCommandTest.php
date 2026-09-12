<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\DeployReportNotifyCommand;
use App\Interface\DeployNotificationMailerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * TDD RED → GREEN : parse le fichier de rapport écrit par bin/deploy-nightly.sh
 * sur chaque instance (#421), envoie le récapitulatif, supprime le fichier.
 */
final class DeployReportNotifyCommandTest extends TestCase
{
    private string $reportFile;

    protected function setUp(): void
    {
        $this->reportFile = sys_get_temp_dir() . '/deploy-report-test-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->reportFile);
    }

    public function testParsesValidReportFileAndSendsIt(): void
    {
        file_put_contents(
            $this->reportFile,
            "yannick|ok|-|abc1234\ncoralie|failed|migrations|-\nelea|skipped|-|-\n"
        );

        $mailer = $this->createMock(DeployNotificationMailerInterface::class);
        $mailer->expects(self::once())->method('sendDeployReport')->with([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
            ['instance' => 'coralie', 'status' => 'failed', 'step' => 'migrations', 'sha' => null],
            ['instance' => 'elea', 'status' => 'skipped', 'step' => null, 'sha' => null],
        ]);

        $tester = $this->commandTester($mailer);
        $tester->execute(['--file' => $this->reportFile]);

        $tester->assertCommandIsSuccessful();
    }

    public function testDeletesReportFileAfterSending(): void
    {
        file_put_contents($this->reportFile, "yannick|ok|-|abc1234\n");

        $mailer = $this->createStub(DeployNotificationMailerInterface::class);
        $tester = $this->commandTester($mailer);
        $tester->execute(['--file' => $this->reportFile]);

        self::assertFileDoesNotExist($this->reportFile);
    }

    public function testMissingFileExitsSuccessfullyWithWarning(): void
    {
        $mailer = $this->createMock(DeployNotificationMailerInterface::class);
        $mailer->expects(self::never())->method('sendDeployReport');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $tester = $this->commandTester($mailer, $logger);
        $tester->execute(['--file' => $this->reportFile]); // fichier jamais créé

        $tester->assertCommandIsSuccessful();
    }

    public function testMalformedLineIsIgnoredButOthersAreProcessed(): void
    {
        file_put_contents(
            $this->reportFile,
            "yannick|ok|-|abc1234\nligne-mal-formee-sans-pipes\ncoralie|ok|-|def5678\n"
        );

        $mailer = $this->createMock(DeployNotificationMailerInterface::class);
        $mailer->expects(self::once())->method('sendDeployReport')->with([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
            ['instance' => 'coralie', 'status' => 'ok', 'step' => null, 'sha' => 'def5678'],
        ]);

        $tester = $this->commandTester($mailer);
        $tester->execute(['--file' => $this->reportFile]);

        $tester->assertCommandIsSuccessful();
    }

    private function commandTester(
        DeployNotificationMailerInterface $mailer,
        ?LoggerInterface $logger = null,
    ): CommandTester {
        $command = new DeployReportNotifyCommand($mailer, $logger ?? $this->createStub(LoggerInterface::class));
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:deploy-queue:notify'));
    }
}
