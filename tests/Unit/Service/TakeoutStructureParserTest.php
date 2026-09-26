<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Takeout\TakeoutStructureParser;
use PHPUnit\Framework\TestCase;

/**
 * #327 — parcourt l'arborescence extraite d'un export Google Takeout,
 * distingue médias / métadonnées / fichiers à ignorer, associe chaque
 * média à son .supplemental-metadata.json s'il existe.
 *
 * Un seul parcours disque (cf. plan-327-takeout-import.md, contrainte
 * d'efficacité) : classification et association se font dans le même
 * passage, jamais deux relectures séparées de l'arborescence.
 */
final class TakeoutStructureParserTest extends TestCase
{
    private TakeoutStructureParser $parser;
    private string $root;

    protected function setUp(): void
    {
        $this->parser = new TakeoutStructureParser();
        $this->root = tempnam(sys_get_temp_dir(), 'takeout_root_');
        unlink($this->root);
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
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

    private function touch(string $relativePath, string $content = 'x'): void
    {
        $fullPath = $this->root . '/' . $relativePath;
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $content);
    }

    public function testFindsMediaWithAssociatedJsonMetadata(): void
    {
        $this->touch('Google Photos/Vacances 2026/photo1.jpg');
        $this->touch('Google Photos/Vacances 2026/photo1.jpg.supplemental-metadata.json', '{}');

        $result = $this->parser->parse($this->root);

        $this->assertCount(1, $result->mediaEntries);
        $this->assertStringEndsWith('photo1.jpg', $result->mediaEntries[0]->mediaPath);
        $this->assertStringEndsWith('photo1.jpg.supplemental-metadata.json', $result->mediaEntries[0]->metadataPath);
    }

    public function testFindsMediaWithoutJsonMetadata(): void
    {
        $this->touch('Google Photos/Vacances 2026/photo2.jpg');

        $result = $this->parser->parse($this->root);

        $this->assertCount(1, $result->mediaEntries);
        $this->assertNull($result->mediaEntries[0]->metadataPath);
    }

    public function testIgnoresAlbumMetadataJson(): void
    {
        $this->touch('Google Photos/Vacances 2026/photo1.jpg');
        $this->touch('Google Photos/Vacances 2026/metadata.json', '{}');

        $result = $this->parser->parse($this->root);

        $this->assertCount(1, $result->mediaEntries);
        $this->assertSame(1, $result->ignoredCount);
    }

    public function testIgnoresPrintSubscriptionsJson(): void
    {
        $this->touch('Google Photos/print-subscriptions.json', '{}');

        $result = $this->parser->parse($this->root);

        $this->assertCount(0, $result->mediaEntries);
        $this->assertSame(1, $result->ignoredCount);
    }

    public function testFindsMultipleMediaAcrossNestedFolders(): void
    {
        $this->touch('Google Photos/2025/a.jpg');
        $this->touch('Google Photos/2025/a.jpg.supplemental-metadata.json', '{}');
        $this->touch('Google Photos/2026/Album/b.png');
        $this->touch('Google Photos/2026/Album/c.mp4');

        $result = $this->parser->parse($this->root);

        $this->assertCount(3, $result->mediaEntries);
    }

    public function testReturnsEmptyResultForEmptyDirectory(): void
    {
        $result = $this->parser->parse($this->root);

        $this->assertCount(0, $result->mediaEntries);
        $this->assertSame(0, $result->ignoredCount);
    }
}
