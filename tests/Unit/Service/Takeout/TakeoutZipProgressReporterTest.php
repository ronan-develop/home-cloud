<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Takeout;

use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use App\Service\Takeout\TakeoutZipProgressReporter;
use PHPUnit\Framework\TestCase;

/**
 * #545 : détail de progression par ZIP (nom + entrées extraites/totales),
 * affiché en plus des compteurs globaux (extractedZipCount/totalZipCount)
 * sur /import/takeout — une seule barre globale ne montrait pas quel ZIP
 * était en cours ni son avancement interne (#546 introduit un batch limité
 * par appel, donc un ZIP peut rester "en cours" plusieurs minutes).
 */
final class TakeoutZipProgressReporterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc_zip_progress_test_' . uniqid();
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
        $owner = new User('takeout-progress@example.com', 'Takeout Progress');

        return new TakeoutImport($owner);
    }

    private function makeZip(string $path, array $entries): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    public function testReturnsEmptyArrayWhenNoZip(): void
    {
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $reporter = new TakeoutZipProgressReporter($locator);
        $import = $this->makeImport();

        self::assertSame([], $reporter->reportFor($import));
    }

    public function testFullyExtractedZipsBeforeExtractedCountAreComplete(): void
    {
        $import = $this->makeImport();
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $dir = $locator->dirFor($import);
        mkdir($dir, 0777, true);
        $this->makeZip($dir . '/takeout-001.zip', ['a.jpg' => 'x', 'b.jpg' => 'y']);
        $this->makeZip($dir . '/takeout-002.zip', ['c.jpg' => 'z']);
        $import->markExtracting(2);
        $import->incrementExtractedZipCount(); // takeout-001 terminé

        $reporter = new TakeoutZipProgressReporter($locator);
        $report = $reporter->reportFor($import);

        self::assertCount(2, $report);
        self::assertSame('takeout-001.zip', $report[0]['name']);
        self::assertSame(2, $report[0]['extractedEntries']);
        self::assertSame(2, $report[0]['totalEntries']);
        self::assertTrue($report[0]['isComplete']);
    }

    public function testCurrentZipReadsPartialProgressFromDisk(): void
    {
        $import = $this->makeImport();
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $dir = $locator->dirFor($import);
        mkdir($dir, 0777, true);
        $this->makeZip($dir . '/takeout-001.zip', ['a.jpg' => 'x', 'b.jpg' => 'y']);
        $this->makeZip($dir . '/takeout-002.zip', ['c.jpg' => 'z', 'd.jpg' => 'w', 'e.jpg' => 'v']);
        $import->markExtracting(2);
        $import->incrementExtractedZipCount(); // takeout-001 terminé
        // takeout-002 en cours, batch limité (#546) : 2 entrées extraites sur 3
        // — .progress vit dans sys_get_temp_dir()/takeout-import-<uuid>/, PAS
        // dans le dossier des ZIP source (TakeoutImportExtractHandler).
        $workDir = sys_get_temp_dir() . '/takeout-import-' . $import->getId()->toRfc4122();
        mkdir($workDir, 0777, true);
        file_put_contents($workDir . '/.progress', '1');

        try {
            $reporter = new TakeoutZipProgressReporter($locator);
            $report = $reporter->reportFor($import);

            self::assertSame('takeout-002.zip', $report[1]['name']);
            self::assertSame(2, $report[1]['extractedEntries']);
            self::assertSame(3, $report[1]['totalEntries']);
            self::assertFalse($report[1]['isComplete']);
        } finally {
            $this->removeDir($workDir);
        }
    }

    public function testZipsNotYetStartedHaveZeroExtractedEntries(): void
    {
        $import = $this->makeImport();
        $locator = new TakeoutImportTmpDirLocator($this->tmpDir);
        $dir = $locator->dirFor($import);
        mkdir($dir, 0777, true);
        $this->makeZip($dir . '/takeout-001.zip', ['a.jpg' => 'x']);
        $this->makeZip($dir . '/takeout-002.zip', ['b.jpg' => 'y', 'c.jpg' => 'z']);
        $import->markExtracting(2);
        // Aucun ZIP encore terminé, aucun .progress : rien n'a démarré

        $reporter = new TakeoutZipProgressReporter($locator);
        $report = $reporter->reportFor($import);

        self::assertSame(0, $report[0]['extractedEntries']);
        self::assertSame(0, $report[1]['extractedEntries']);
        self::assertFalse($report[0]['isComplete']);
        self::assertFalse($report[1]['isComplete']);
    }
}
