<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RawPreviewCache;
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
        $dir = $this->storageDir . '/previews';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        if (is_dir($this->storageDir)) {
            rmdir($this->storageDir);
        }
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

        $files = glob($this->storageDir . '/previews/*') ?: [];
        $this->assertCount(1, $files);

        // Un chemin source contient des slashes : le nom de cache doit être plat,
        // sans arborescence à créer ni traversée de répertoire possible.
        $name = basename($files[0]);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('..', $name);
    }
}
