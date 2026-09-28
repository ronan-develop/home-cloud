<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TakeoutImportNightlyDispatchCommand;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ServerLoadChecker;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;
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
    ): CommandTester {
        $command = new TakeoutImportNightlyDispatchCommand($repository, $tmpDirLocator, $bus, $loadChecker ?? $this->calmChecker());
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:takeout:nightly-dispatch'));
    }
}
