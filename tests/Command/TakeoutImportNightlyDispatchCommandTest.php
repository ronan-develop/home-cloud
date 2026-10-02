<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TakeoutImportNightlyDispatchCommand;
use App\Entity\TakeoutDispatchLog;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutDispatchLogRepository;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ServerLoadChecker;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * #522/#524 : traiter les imports Takeout dès que le serveur est calme,
 * plutôt que toujours attendre la nuit — un import volumineux se faisait
 * tuer (SIGKILL) par le LVE du mutualisé o2switch même en pleine journée
 * sous forte charge, indépendamment du découpage par ZIP (#520). Cette
 * commande (appelée toutes les 15 min, pas seulement la nuit) vérifie le CPU
 * via ServerLoadChecker à chaque exécution et dispatche seulement si calme —
 * comportement dynamique, pas une simple bascule jour/nuit binaire : un
 * import reste "scheduled" et retenté au cycle suivant tant que le serveur
 * est chargé, quelle que soit l'heure.
 */
final class TakeoutImportNightlyDispatchCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc_nightly_dispatch_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeImport(): TakeoutImport
    {
        return new TakeoutImport(new User('takeout-nightly@example.com', 'Takeout'));
    }

    /**
     * @param string[] $zipFilenames
     */
    private function makeZipsFor(TakeoutImportTmpDirLocator $locator, TakeoutImport $import, array $zipFilenames): void
    {
        $dir = $locator->dirFor($import);
        mkdir($dir, 0777, true);
        foreach ($zipFilenames as $filename) {
            file_put_contents($dir . '/' . $filename, 'content');
        }
    }

    public function testDispatchesExtractMessageForEachScheduledImport(): void
    {
        $import1 = $this->makeImport();
        $import1->markScheduled();
        $import2 = $this->makeImport();
        $import2->markScheduled();

        $repository = $this->createMock(TakeoutImportRepository::class);
        $repository->expects($this->once())
            ->method('findAllScheduled')
            ->willReturn([$import1, $import2]);

        $tmpDirLocator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $this->makeZipsFor($tmpDirLocator, $import1, ['a1.zip', 'a2.zip']);
        $this->makeZipsFor($tmpDirLocator, $import2, ['b1.zip']);

        $bus = $this->createMock(MessageBusInterface::class);
        $dispatched = [];
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$dispatched) {
                $dispatched[] = $message;

                return new Envelope(new \stdClass());
            });

        $tester = $this->commandTester($repository, $tmpDirLocator, $bus);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertCount(2, $dispatched);

        $this->assertInstanceOf(TakeoutImportExtractMessage::class, $dispatched[0]);
        $this->assertSame((string) $import1->getId(), $dispatched[0]->takeoutImportId);
        $this->assertStringEndsWith('a1.zip', $dispatched[0]->zipPath);
        $this->assertCount(1, $dispatched[0]->remainingZipPaths);
        $this->assertStringEndsWith('a2.zip', $dispatched[0]->remainingZipPaths[0]);

        $this->assertInstanceOf(TakeoutImportExtractMessage::class, $dispatched[1]);
        $this->assertSame((string) $import2->getId(), $dispatched[1]->takeoutImportId);
        $this->assertStringEndsWith('b1.zip', $dispatched[1]->zipPath);
        $this->assertSame([], $dispatched[1]->remainingZipPaths);
    }

    public function testReportsZeroWhenNothingScheduled(): void
    {
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('findAllScheduled')->willReturn([]);

        $tmpDirLocator = new TakeoutImportTmpDirLocator($this->tmpDir);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $tester = $this->commandTester($repository, $tmpDirLocator, $bus, $this->calmChecker());
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0', $tester->getDisplay());
    }

    // #524 : comportement dynamique, pas une bascule jour/nuit binaire —
    // tant que le serveur est chargé, un import reste "scheduled" et sera
    // retenté au prochain cycle (toutes les 15 min), quelle que soit l'heure.
    public function testDoesNotDispatchAnyImportWhenServerIsNotCalmEnough(): void
    {
        $import = $this->makeImport();
        $import->markScheduled();

        // findAllScheduled() n'est même pas appelée : inutile d'interroger
        // la base si de toute façon rien ne sera dispatché ce cycle-ci.
        $repository = $this->createMock(TakeoutImportRepository::class);
        $repository->expects($this->never())->method('findAllScheduled');

        $tmpDirLocator = new TakeoutImportTmpDirLocator($this->tmpDir);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $tester = $this->commandTester($repository, $tmpDirLocator, $bus, $this->loadedChecker());
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(TakeoutImport::STATUS_SCHEDULED, $import->getStatus());
        $this->assertStringContainsString('trop chargé', $tester->getDisplay());
    }

    private function calmChecker(): ServerLoadChecker
    {
        return new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => [1.0, 1.0, 1.0]);
    }

    private function loadedChecker(): ServerLoadChecker
    {
        return new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => [9.86, 10.45, 10.48]);
    }

    private function commandTester(
        TakeoutImportRepository $repository,
        TakeoutImportTmpDirLocator $tmpDirLocator,
        MessageBusInterface $bus,
        ?ServerLoadChecker $loadChecker = null,
        ?TakeoutDispatchLogRepository $logRepository = null,
        ?LoggerInterface $logger = null,
    ): CommandTester {
        $command = new TakeoutImportNightlyDispatchCommand(
            $repository,
            $tmpDirLocator,
            $bus,
            $loadChecker ?? $this->calmChecker(),
            $logRepository ?? $this->createStub(TakeoutDispatchLogRepository::class),
            $logger ?? new NullLogger(),
        );
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:takeout:nightly-dispatch'));
    }

    private function busStub(): MessageBusInterface
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        return $bus;
    }

    // #528 : une ligne d'historique par cycle, y compris "idle" (rien en
    // attente) — c'est aussi l'échantillonnage de charge de #547.
    public function testLogsIdleCycleWhenNothingScheduled(): void
    {
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('findAllScheduled')->willReturn([]);

        $logs = [];
        $logRepository = $this->createMock(TakeoutDispatchLogRepository::class);
        $logRepository->expects($this->once())->method('save')->willReturnCallback(function (TakeoutDispatchLog $log) use (&$logs) {
            $logs[] = $log;
        });

        $tester = $this->commandTester($repository, new TakeoutImportTmpDirLocator($this->tmpDir), $this->busStub(), $this->calmChecker(), $logRepository);
        $tester->execute([]);

        $this->assertSame(TakeoutDispatchLog::OUTCOME_IDLE, $logs[0]->getOutcome());
        $this->assertSame([1.0, 1.0, 1.0], $logs[0]->getLoadAverage());
        $this->assertSame(3.0, $logs[0]->getThreshold());
        $this->assertSame(0, $logs[0]->getScheduledCount());
        $this->assertSame(0, $logs[0]->getDispatchedCount());
    }

    public function testLogsDispatchedCycleWithCounts(): void
    {
        $imports = [$this->makeImport(), $this->makeImport()];
        $tmpDirLocator = new TakeoutImportTmpDirLocator($this->tmpDir);
        foreach ($imports as $import) {
            $import->markScheduled();
            $this->makeZipsFor($tmpDirLocator, $import, ['a.zip']);
        }
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('findAllScheduled')->willReturn($imports);

        $logs = [];
        $logRepository = $this->createMock(TakeoutDispatchLogRepository::class);
        $logRepository->expects($this->once())->method('save')->willReturnCallback(function (TakeoutDispatchLog $log) use (&$logs) {
            $logs[] = $log;
        });

        $tester = $this->commandTester($repository, $tmpDirLocator, $this->busStub(), $this->calmChecker(), $logRepository);
        $tester->execute([]);

        $this->assertSame(TakeoutDispatchLog::OUTCOME_DISPATCHED, $logs[0]->getOutcome());
        $this->assertSame(2, $logs[0]->getScheduledCount());
        $this->assertSame(2, $logs[0]->getDispatchedCount());
    }

    public function testLogsDeferredCycleWithScheduledCountWithoutLoadingImports(): void
    {
        $repository = $this->createMock(TakeoutImportRepository::class);
        $repository->expects($this->never())->method('findAllScheduled');
        $repository->method('countScheduled')->willReturn(3);

        $logs = [];
        $logRepository = $this->createMock(TakeoutDispatchLogRepository::class);
        $logRepository->expects($this->once())->method('save')->willReturnCallback(function (TakeoutDispatchLog $log) use (&$logs) {
            $logs[] = $log;
        });

        $tester = $this->commandTester($repository, new TakeoutImportTmpDirLocator($this->tmpDir), $this->busStub(), $this->loadedChecker(), $logRepository);
        $tester->execute([]);

        $this->assertSame(TakeoutDispatchLog::OUTCOME_DEFERRED, $logs[0]->getOutcome());
        $this->assertSame([9.86, 10.45, 10.48], $logs[0]->getLoadAverage());
        $this->assertSame(3, $logs[0]->getScheduledCount());
        $this->assertSame(0, $logs[0]->getDispatchedCount());
    }

    public function testLogsDeferredWithNullLoadWhenMeasureUnavailable(): void
    {
        $repository = $this->createStub(TakeoutImportRepository::class);
        $checker = new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => false);

        $logs = [];
        $logRepository = $this->createStub(TakeoutDispatchLogRepository::class);
        $logRepository->method('save')->willReturnCallback(function (TakeoutDispatchLog $log) use (&$logs) {
            $logs[] = $log;
        });

        $tester = $this->commandTester($repository, new TakeoutImportTmpDirLocator($this->tmpDir), $this->busStub(), $checker, $logRepository);
        $tester->execute([]);

        $this->assertSame(TakeoutDispatchLog::OUTCOME_DEFERRED, $logs[0]->getOutcome());
        $this->assertNull($logs[0]->getLoadAverage());
    }

    public function testPurgesLogsOlderThanRetentionAtEachCycle(): void
    {
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('findAllScheduled')->willReturn([]);

        $logRepository = $this->createMock(TakeoutDispatchLogRepository::class);
        $logRepository->expects($this->once())
            ->method('purgeOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $threshold): bool {
                $expected = new \DateTimeImmutable('-' . TakeoutDispatchLog::PURGE_AFTER_DAYS . ' days');

                return abs($threshold->getTimestamp() - $expected->getTimestamp()) < 5;
            }));

        $tester = $this->commandTester($repository, new TakeoutImportTmpDirLocator($this->tmpDir), $this->busStub(), $this->calmChecker(), $logRepository);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    // Rollback : l'historique est de l'observation, jamais une condition du
    // dispatch — une table indisponible ne doit pas bloquer les imports.
    public function testStillDispatchesWhenLogWriteFails(): void
    {
        $import = $this->makeImport();
        $import->markScheduled();
        $tmpDirLocator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $this->makeZipsFor($tmpDirLocator, $import, ['a.zip']);
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('findAllScheduled')->willReturn([$import]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $logRepository = $this->createStub(TakeoutDispatchLogRepository::class);
        $logRepository->method('save')->willThrowException(new \RuntimeException('table absente'));
        $logRepository->method('purgeOlderThan')->willThrowException(new \RuntimeException('table absente'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error');

        $tester = $this->commandTester($repository, $tmpDirLocator, $bus, $this->calmChecker(), $logRepository, $logger);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }
}
