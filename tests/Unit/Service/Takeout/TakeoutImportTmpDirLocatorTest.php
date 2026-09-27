<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Takeout;

use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use PHPUnit\Framework\TestCase;

final class TakeoutImportTmpDirLocatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc_locator_test_' . uniqid();
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
        $owner = new User('takeout-locator@example.com', 'Takeout Locator');
        return new TakeoutImport($owner);
    }

    public function testDirForBuildsPathFromImportId(): void
    {
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $import = $this->makeImport();

        $dir = $locator->dirFor($import);

        self::assertSame($this->tmpDir . '/' . $import->getId()->toRfc4122(), $dir);
    }

    public function testZipPathsForReturnsEmptyArrayWhenDirDoesNotExist(): void
    {
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $import = $this->makeImport();

        self::assertSame([], $locator->zipPathsFor($import));
    }

    public function testZipPathsForListsOnlyZipFiles(): void
    {
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $import = $this->makeImport();
        $importDir = $locator->dirFor($import);
        mkdir($importDir, 0777, true);
        file_put_contents($importDir . '/takeout-001.zip', 'content');
        file_put_contents($importDir . '/takeout-002.zip', 'content');
        file_put_contents($importDir . '/takeout-001.zip.progress', '0');

        $zipPaths = $locator->zipPathsFor($import);

        self::assertCount(2, $zipPaths);
    }
}
