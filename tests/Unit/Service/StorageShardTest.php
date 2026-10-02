<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\File\StorageShard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * #609 : aucun répertoire de stockage ne doit approcher 20 000 fichiers
 * (limite o2switch). Le shard est calculé sur la FIN du nom (partie aléatoire
 * de l'UUID v7), jamais sur son début (horodatage commun à tout un import).
 */
final class StorageShardTest extends TestCase
{
    public function testShardIsLastTwoHexCharsOfUuidStem(): void
    {
        $this->assertSame('ef', StorageShard::forFilename('0192f3a0-7c1b-7d2e-8a4f-1234567890ef.jpg'));
        $this->assertSame('ef', StorageShard::forFilename('0192f3a0-7c1b-7d2e-8a4f-1234567890EF.nef'));
    }

    public function testShardIgnoresExtensionAndDirectory(): void
    {
        $this->assertSame('ab', StorageShard::forFilename('2026/10/0192f3a0-7c1b-7d2e-8a4f-1234567890ab.pdf'));
        $this->assertSame('ab', StorageShard::forFilename('thumbs/0192f3a0-7c1b-7d2e-8a4f-1234567890ab.jpg'));
    }

    public function testNonHexStemFallsBackToStableHash(): void
    {
        $shard = StorageShard::forFilename('photo.nef');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{2}$/', $shard);
        $this->assertSame($shard, StorageShard::forFilename('autre/dossier/photo.nef'));
    }

    public function testConsecutiveUuidV7SpreadOverManyShards(): void
    {
        $shards = [];
        for ($i = 0; $i < 5000; ++$i) {
            $shards[StorageShard::forFilename(Uuid::v7()->toRfc4122().'.jpg')] = true;
        }

        $this->assertGreaterThan(200, count($shards), 'Un import en rafale doit se répartir sur ~256 sous-dossiers');
    }

    public function testOriginalPathHasNoDateAndOneShardLevel(): void
    {
        $this->assertSame(
            'ef/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.jpg',
            StorageShard::originalPath('0192f3a0-7c1b-7d2e-8a4f-1234567890ef.jpg'),
        );
    }

    public function testThumbnailPathIsShardedUnderThumbs(): void
    {
        $this->assertSame(
            'thumbs/ef/0192f3a0-7c1b-7d2e-8a4f-1234567890ef.jpg',
            StorageShard::thumbnailPath('0192f3a0-7c1b-7d2e-8a4f-1234567890ef.jpg'),
        );
    }

    public function testShardedPathOfLegacyPath(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';

        $this->assertSame("ef/{$uuid}.jpg", StorageShard::shardedPathOf("2026/10/{$uuid}.jpg"));
        $this->assertSame("thumbs/ef/{$uuid}.jpg", StorageShard::shardedPathOf("thumbs/{$uuid}.jpg"));
    }

    public function testShardedPathOfAlreadyShardedPathIsUnchanged(): void
    {
        $uuid = '0192f3a0-7c1b-7d2e-8a4f-1234567890ef';

        $this->assertSame("ef/{$uuid}.jpg", StorageShard::shardedPathOf("ef/{$uuid}.jpg"));
        $this->assertSame("thumbs/ef/{$uuid}.jpg", StorageShard::shardedPathOf("thumbs/ef/{$uuid}.jpg"));
    }

    public function testShardedPathOfUnknownLayoutIsUnchanged(): void
    {
        $this->assertSame('demo/photo.jpg', StorageShard::shardedPathOf('demo/photo.jpg'));
    }
}
