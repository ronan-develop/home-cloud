<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\File\StorageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * #609 : les nouveaux fichiers s'écrivent au format réparti `<shard>/<uuid>.<ext>`,
 * les anciens (`AAAA/MM/<uuid>.<ext>`) restent lisibles tant qu'ils ne sont pas migrés.
 */
final class StorageServiceShardingTest extends TestCase
{
    private string $storageDir;
    private string $tmpDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/hc-shard-'.uniqid();
        $this->storageDir = $base.'/storage';
        $this->tmpDir = $base.'/tmp';
        mkdir($this->storageDir, 0755, true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->storageDir));
    }

    public function testStoreWritesToShardedPathWithoutDate(): void
    {
        $service = new StorageService($this->storageDir);

        $result = $service->store($this->upload('doc.txt', 'bonjour'));

        $this->assertMatchesRegularExpression('#^[0-9a-f]{2}/[0-9a-f-]{36}\.txt$#', $result['path']);
        $this->assertSame('bonjour', file_get_contents($this->storageDir.'/'.$result['path']));
    }

    public function testNeutralizedFileIsAlsoSharded(): void
    {
        $service = new StorageService($this->storageDir);

        $result = $service->store($this->upload('page.html', '<b>x</b>'));

        $this->assertTrue($result['neutralized']);
        $this->assertMatchesRegularExpression('#^[0-9a-f]{2}/[0-9a-f-]{36}\.bin$#', $result['path']);
    }

    public function testShardMatchesUuidEndInPath(): void
    {
        $service = new StorageService($this->storageDir);

        $path = $service->store($this->upload('a.txt', 'a'))['path'];

        $this->assertSame(substr($path, 0, 2), substr($path, -6, 2), 'Le shard est la fin du nom (avant ".txt")');
    }

    public function testGetAbsolutePathResolvesLegacyPathWhenFileStillThere(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';
        mkdir($this->storageDir.'/2026/10', 0755, true);
        file_put_contents($this->storageDir."/2026/10/{$uuid}.jpg", 'old');

        $absolute = (new StorageService($this->storageDir))->getAbsolutePath("2026/10/{$uuid}.jpg");

        $this->assertSame(realpath($this->storageDir)."/2026/10/{$uuid}.jpg", $absolute);
    }

    public function testGetAbsolutePathFallsBackToShardedPathWhenLegacyFileWasMoved(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';
        mkdir($this->storageDir.'/ef', 0755, true);
        file_put_contents($this->storageDir."/ef/{$uuid}.jpg", 'moved');

        $absolute = (new StorageService($this->storageDir))->getAbsolutePath("2026/10/{$uuid}.jpg");

        $this->assertSame(realpath($this->storageDir)."/ef/{$uuid}.jpg", $absolute);
    }

    public function testGetAbsolutePathFallsBackForLegacyThumbnail(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';
        mkdir($this->storageDir.'/thumbs/ef', 0755, true);
        file_put_contents($this->storageDir."/thumbs/ef/{$uuid}.jpg", 'thumb');

        $absolute = (new StorageService($this->storageDir))->getAbsolutePath("thumbs/{$uuid}.jpg");

        $this->assertSame(realpath($this->storageDir)."/thumbs/ef/{$uuid}.jpg", $absolute);
    }

    public function testGetAbsolutePathStillReturnsCandidateWhenNothingExists(): void
    {
        $absolute = (new StorageService($this->storageDir))->getAbsolutePath('2026/10/absent.jpg');

        $this->assertSame($this->storageDir.'/2026/10/absent.jpg', $absolute);
    }

    public function testPathTraversalIsStillRejected(): void
    {
        file_put_contents(dirname($this->storageDir).'/secret.txt', 'x');

        $this->expectException(\RuntimeException::class);

        (new StorageService($this->storageDir))->getAbsolutePath('../secret.txt');
    }

    public function testDeleteRemovesFileMovedToShardedPath(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';
        mkdir($this->storageDir.'/ef', 0755, true);
        file_put_contents($this->storageDir."/ef/{$uuid}.jpg", 'moved');

        (new StorageService($this->storageDir))->delete("2026/10/{$uuid}.jpg");

        $this->assertFileDoesNotExist($this->storageDir."/ef/{$uuid}.jpg");
    }

    public function testDeleteRemovesLegacyFile(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';
        mkdir($this->storageDir.'/2026/10', 0755, true);
        file_put_contents($this->storageDir."/2026/10/{$uuid}.jpg", 'old');

        (new StorageService($this->storageDir))->delete("2026/10/{$uuid}.jpg");

        $this->assertFileDoesNotExist($this->storageDir."/2026/10/{$uuid}.jpg");
    }

    private function upload(string $name, string $content): UploadedFile
    {
        $path = $this->tmpDir.'/'.uniqid('up-').'-'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
