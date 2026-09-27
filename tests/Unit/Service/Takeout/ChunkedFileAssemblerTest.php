<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Takeout;

use App\Service\Takeout\ChunkedFileAssembler;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN (#466) : un ZIP Google Takeout peut dépasser 512M (plafond
 * dur de l'hébergement mutualisé o2switch, non contournable via .user.ini —
 * confirmé en conditions réelles avec des archives ~2GB). Le front découpe
 * chaque fichier en tranches envoyées séquentiellement ; ce service les
 * réassemble côté serveur par écriture en append, dans l'ordre strict
 * attendu, idempotent sur un retry réseau (même chunkIndex renvoyé deux fois
 * = no-op silencieux, pas de duplication).
 */
final class ChunkedFileAssemblerTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = sys_get_temp_dir() . '/hc_chunk_test_' . uniqid();
        mkdir($this->targetDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->targetDir . '/*') ?: []);
        @rmdir($this->targetDir);
    }

    public function testAssemblesChunksInOrderIntoSingleFile(): void
    {
        $assembler = new ChunkedFileAssembler();
        $targetPath = $this->targetDir . '/takeout.zip';

        $isComplete1 = $assembler->appendChunk($targetPath, 0, 3, 'AAA');
        $isComplete2 = $assembler->appendChunk($targetPath, 1, 3, 'BBB');
        $isComplete3 = $assembler->appendChunk($targetPath, 2, 3, 'CCC');

        self::assertFalse($isComplete1);
        self::assertFalse($isComplete2);
        self::assertTrue($isComplete3);
        self::assertSame('AAABBBCCC', file_get_contents($targetPath));
    }

    public function testRetryingSameChunkIndexIsIdempotent(): void
    {
        $assembler = new ChunkedFileAssembler();
        $targetPath = $this->targetDir . '/takeout.zip';

        $assembler->appendChunk($targetPath, 0, 2, 'AAA');
        // Retry réseau : le front renvoie le même chunk 0 une seconde fois.
        $assembler->appendChunk($targetPath, 0, 2, 'AAA');
        $isComplete = $assembler->appendChunk($targetPath, 1, 2, 'BBB');

        self::assertTrue($isComplete);
        self::assertSame('AAABBB', file_get_contents($targetPath), 'Le chunk 0 rejoué ne doit pas être dupliqué');
    }

    public function testRejectsChunkArrivingOutOfOrder(): void
    {
        $assembler = new ChunkedFileAssembler();
        $targetPath = $this->targetDir . '/takeout.zip';

        $assembler->appendChunk($targetPath, 0, 3, 'AAA');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('out of order');

        // Le chunk 2 arrive avant le chunk 1 : rejeté plutôt que de corrompre
        // silencieusement le fichier assemblé.
        $assembler->appendChunk($targetPath, 2, 3, 'CCC');
    }

    public function testSingleChunkFileIsImmediatelyComplete(): void
    {
        $assembler = new ChunkedFileAssembler();
        $targetPath = $this->targetDir . '/takeout.zip';

        $isComplete = $assembler->appendChunk($targetPath, 0, 1, 'contenu entier');

        self::assertTrue($isComplete);
        self::assertSame('contenu entier', file_get_contents($targetPath));
    }
}
