<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Media\RawPreviewCache;
use PHPUnit\Framework\TestCase;

/**
 * Redresser et redimensionner une preview coûte ~1 s : sans cache, un diaporama
 * repaierait ce prix à chaque photo, à chaque passage.
 *
 * Le nom du fichier de cache est dérivé du chemin source plutôt que stocké en
 * base : pas de migration ni de champ à maintenir, et le cache reste un détail
 * d'implémentation qu'on peut vider à tout moment sans rien casser.
 */
final class RawPreviewCacheTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/hc-preview-cache-' . uniqid();
        mkdir($this->storageDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->storageDir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->storageDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->storageDir);
    }

    public function testStoresAndReturnsPreview(): void
    {
        $cache = new RawPreviewCache($this->storageDir);

        $this->assertNull($cache->get('2026/07/photo.nef'), 'Cache vide au départ');

        $cache->put('2026/07/photo.nef', 'jpeg-bytes');

        $this->assertSame('jpeg-bytes', $cache->get('2026/07/photo.nef'));
    }

    public function testDistinguishesSourceFiles(): void
    {
        $cache = new RawPreviewCache($this->storageDir);

        $cache->put('2026/07/a.nef', 'preview-a');
        $cache->put('2026/07/b.nef', 'preview-b');

        $this->assertSame('preview-a', $cache->get('2026/07/a.nef'));
        $this->assertSame('preview-b', $cache->get('2026/07/b.nef'));
    }

    public function testEvictRemovesCachedPreview(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/photo.nef', 'jpeg-bytes');

        $cache->evict('2026/07/photo.nef');

        $this->assertNull($cache->get('2026/07/photo.nef'));
    }

    public function testEvictIsSilentWhenNothingCached(): void
    {
        $cache = new RawPreviewCache($this->storageDir);

        // Appelé à chaque suppression de média, y compris pour les JPEG qui
        // n'ont jamais de preview en cache : ne doit rien casser.
        $cache->evict('2026/07/jamais-vu.nef');

        $this->assertNull($cache->get('2026/07/jamais-vu.nef'));
    }

    public function testCacheKeyDoesNotLeakSourcePathStructure(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/photo.nef', 'jpeg-bytes');

        $files = glob($this->storageDir . '/previews/*/*.jpg') ?: [];
        $this->assertCount(1, $files);

        // Un chemin source contient des slashes : le nom de cache doit être plat,
        // sans arborescence à créer ni traversée de répertoire possible.
        $name = basename($files[0]);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('..', $name);
    }

    // ── #609 : répartition en sous-dossiers, clé stable à travers un déplacement ──

    public function testPreviewIsStoredInShardSubdirectory(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/photo.nef', 'jpeg-bytes');

        $this->assertCount(0, glob($this->storageDir . '/previews/*.jpg') ?: [], 'Plus de fichier à plat');
        $this->assertCount(1, glob($this->storageDir . '/previews/*/*.jpg') ?: []);
        $this->assertMatchesRegularExpression('#/previews/[0-9a-f]{2}/#', (glob($this->storageDir . '/previews/*/*.jpg') ?: [''])[0]);
    }

    public function testKeyIsStableWhenSourceIsMovedToShardedPath(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.nef', 'jpeg-bytes');

        // app:storage:shard déplace l'original : la preview déjà calculée reste valable.
        $this->assertSame('jpeg-bytes', $cache->get('ef/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.nef'));
    }

    public function testEvictFromMovedPathRemovesPreviewCachedUnderLegacyPath(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.nef', 'jpeg-bytes');

        $cache->evict('ef/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.nef');

        $this->assertNull($cache->get('2026/07/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.nef'));
    }

    public function testEvictAlsoRemovesLegacyFlatEntry(): void
    {
        $legacyDir = $this->storageDir . '/previews';
        mkdir($legacyDir, 0755, true);
        $legacy = $legacyDir . '/' . hash('xxh128', '2026/07/old.nef') . '.jpg';
        file_put_contents($legacy, 'old-cache');

        (new RawPreviewCache($this->storageDir))->evict('2026/07/old.nef');

        $this->assertFileDoesNotExist($legacy);
    }

    public function testPurgeLegacyRemovesOnlyFlatEntries(): void
    {
        $cache = new RawPreviewCache($this->storageDir);
        $cache->put('2026/07/new.nef', 'new-cache');
        file_put_contents($this->storageDir . '/previews/' . hash('xxh128', '2026/07/old.nef') . '.jpg', 'old-cache');

        $this->assertSame(1, $cache->purgeLegacyFlatEntries());

        $this->assertSame('new-cache', $cache->get('2026/07/new.nef'));
        $this->assertCount(0, glob($this->storageDir . '/previews/*.jpg') ?: []);
    }

    public function testPurgeLegacyIsSilentWithoutCacheDirectory(): void
    {
        $this->assertSame(0, (new RawPreviewCache($this->storageDir))->purgeLegacyFlatEntries());
    }
}
