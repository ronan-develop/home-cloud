<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TakeoutImportPurgeAbandonedCommand;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportAbandoner;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Purge des imports Takeout pending abandonnés depuis plus de
 * TakeoutImport::PURGE_AFTER_DAYS jours (#493) : un import jamais repris
 * reste orphelin indéfiniment (fichiers disque + ligne base) sans ce cron.
 *
 * TakeoutImportAbandoner est final (détail interne au domaine, une seule
 * implémentation prévue, pas de frontière DIP à traverser) : testé ici avec
 * un vrai TakeoutImportTmpDirLocator sur dossier temporaire plutôt qu'un
 * mock, pour vérifier le comportement réel (dossier supprimé) au lieu de
 * seulement espionner l'appel.
 */
final class TakeoutImportPurgeAbandonedCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc_purge_cmd_test_' . uniqid();
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
        $owner = new User('takeout-purge-cmd@example.com', 'Takeout Purge');
        return new TakeoutImport($owner);
    }

    public function testAbandonsEachImportOlderThanRetentionWindowAndDeletesItsTmpDir(): void
    {
        $import1 = $this->makeImport();
        $import2 = $this->makeImport();
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);

        $importDir1 = $locator->dirFor($import1);
        mkdir($importDir1, 0777, true);
        file_put_contents($importDir1 . '/takeout-001.zip', 'content');

        $repository = $this->createMock(TakeoutImportRepository::class);
        $repository->expects(self::once())
            ->method('findPendingOlderThan')
            ->with(self::callback(function (\DateTimeImmutable $threshold) {
                $expected = (new \DateTimeImmutable())->modify('-' . TakeoutImport::PURGE_AFTER_DAYS . ' days');

                return abs($expected->getTimestamp() - $threshold->getTimestamp()) < 5;
            }))
            ->willReturn([$import1, $import2]);
        $repository->expects(self::exactly(2))->method('remove');

        $abandoner = new TakeoutImportAbandoner($repository, $locator);
        $tester = $this->commandTester($repository, $abandoner);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('2', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($importDir1);
    }

    public function testReportsZeroWhenNothingToPurge(): void
    {
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $repository = $this->createMock(TakeoutImportRepository::class);
        $repository->method('findPendingOlderThan')->willReturn([]);
        $repository->expects(self::never())->method('remove');

        $abandoner = new TakeoutImportAbandoner($repository, $locator);
        $tester = $this->commandTester($repository, $abandoner);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0', $tester->getDisplay());
    }

    private function commandTester(TakeoutImportRepository $repository, TakeoutImportAbandoner $abandoner): CommandTester
    {
        $command = new TakeoutImportPurgeAbandonedCommand($repository, $abandoner);
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:takeout:purge-abandoned'));
    }
}
