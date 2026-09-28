<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Takeout;

use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportAbandoner;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN (#493) : abandon d'un import Takeout pending — supprime le
 * dossier temporaire (var/takeout-tmp/<uuid>/) et la ligne en base.
 * Extrait de TakeoutImportAbandonController pour être réutilisé par
 * TakeoutImportPurgeAbandonedCommand (purge automatique à 7 jours), sans
 * dupliquer cette logique dans les deux endroits.
 */
final class TakeoutImportAbandonerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc_abandoner_test_' . uniqid();
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
        $owner = new User('takeout-abandoner@example.com', 'Takeout Abandoner');
        return new TakeoutImport($owner);
    }

    public function testDeletesTmpDirAndRemovesFromRepository(): void
    {
        $repository = $this->createMock(TakeoutImportRepository::class);
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $abandoner = new TakeoutImportAbandoner($repository, $locator);

        $import = $this->makeImport();
        $importDir = $locator->dirFor($import);
        mkdir($importDir, 0777, true);
        file_put_contents($importDir . '/takeout-001.zip', 'content');

        $repository->expects(self::once())->method('remove')->with($import);

        $abandoner->abandon($import);

        self::assertDirectoryDoesNotExist($importDir);
    }

    public function testRemovesFromRepositoryEvenWithoutTmpDir(): void
    {
        $repository = $this->createMock(TakeoutImportRepository::class);
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $abandoner = new TakeoutImportAbandoner($repository, $locator);

        $import = $this->makeImport();

        $repository->expects(self::once())->method('remove')->with($import);

        $abandoner->abandon($import);
    }
}
