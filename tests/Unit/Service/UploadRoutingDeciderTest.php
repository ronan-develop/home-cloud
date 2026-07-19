<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\UploadBatch;
use App\Interface\StorageServiceInterface;
use App\Repository\MediaRepository;
use App\Service\ExifService;
use App\Service\MediaProcessor;
use App\Service\ThumbnailService;
use App\Service\UploadRoutingDecider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * Le worker Messenger ne doit tourner que pour les lots lourds. UploadRoutingDecider
 * tranche, côté serveur, entre traitement immédiat (kernel.terminate) et déport au
 * worker : au-dessus d'un seuil de taille cumulée, OU dès qu'un RAW est présent
 * (décodage preview coûteux). La reconnaissance RAW délègue à MediaProcessor::isRaw
 * (seule source de vérité), d'où l'injection d'un vrai MediaProcessor ici.
 */
final class UploadRoutingDeciderTest extends TestCase
{
    private const THRESHOLD = 262144000; // 250 Mo

    private function decider(): UploadRoutingDecider
    {
        $mediaProcessor = new MediaProcessor(
            $this->createStub(MediaRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ExifService::class),
            $this->createStub(ThumbnailService::class),
            $this->createStub(StorageServiceInterface::class),
            $this->createStub(RawPreviewExtractorInterface::class),
        );

        return new UploadRoutingDecider($mediaProcessor, self::THRESHOLD);
    }

    public function testSmallJpegBatchIsImmediate(): void
    {
        $this->assertSame(
            UploadBatch::MODE_IMMEDIATE,
            $this->decider()->decide(10_000_000, ['a.jpg', 'b.jpg']),
        );
    }

    public function testBatchOverThresholdIsDeferred(): void
    {
        $this->assertSame(
            UploadBatch::MODE_DEFERRED,
            $this->decider()->decide(self::THRESHOLD + 1, ['a.jpg']),
        );
    }

    public function testExactThresholdStaysImmediate(): void
    {
        // Seuil strict (> SEUIL) : pile au seuil reste immédiat.
        $this->assertSame(
            UploadBatch::MODE_IMMEDIATE,
            $this->decider()->decide(self::THRESHOLD, ['a.jpg']),
        );
    }

    public function testSingleRawForcesDeferredEvenWhenSmall(): void
    {
        $this->assertSame(
            UploadBatch::MODE_DEFERRED,
            $this->decider()->decide(1_000_000, ['a.jpg', 'shot.nef']),
        );
    }

    public function testRawDetectionIsCaseInsensitive(): void
    {
        $this->assertSame(
            UploadBatch::MODE_DEFERRED,
            $this->decider()->decide(1_000, ['PHOTO.NEF']),
        );
    }

    public function testNonRawExtensionsStayImmediate(): void
    {
        $this->assertSame(
            UploadBatch::MODE_IMMEDIATE,
            $this->decider()->decide(1_000, ['scan.tiff', 'note.txt']),
        );
    }

    public function testEmptyBatchIsImmediate(): void
    {
        $this->assertSame(
            UploadBatch::MODE_IMMEDIATE,
            $this->decider()->decide(0, []),
        );
    }
}
