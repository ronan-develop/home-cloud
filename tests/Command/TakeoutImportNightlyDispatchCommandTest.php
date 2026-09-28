<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TakeoutImportNightlyDispatchCommand;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * #522 : traiter les imports Takeout la nuit pour limiter la contention sur
 * le mutualisé o2switch — un import volumineux se faisait tuer (SIGKILL) par
 * le LVE même en pleine journée sous forte charge, indépendamment du
 * découpage par ZIP (#520). Cette commande cron nocturne dispatche tous les
 * imports "scheduled" (marqués prêts par TakeoutImportStartController, qui
 * ne dispatche plus immédiatement), un ZIP à la fois comme #520.
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

        $tester = $this->commandTester($repository, $tmpDirLocator, $bus);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0', $tester->getDisplay());
    }

    private function commandTester(
        TakeoutImportRepository $repository,
        TakeoutImportTmpDirLocator $tmpDirLocator,
        MessageBusInterface $bus,
    ): CommandTester {
        $command = new TakeoutImportNightlyDispatchCommand($repository, $tmpDirLocator, $bus);
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:takeout:nightly-dispatch'));
    }
}
