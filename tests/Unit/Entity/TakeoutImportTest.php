<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TakeoutImport;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class TakeoutImportTest extends TestCase
{
    public function testConstructorInitializesUuidV7(): void
    {
        $owner = new User('owner@example.com', 'Owner');

        $import = new TakeoutImport($owner);

        $this->assertInstanceOf(UuidV7::class, $import->getId());
    }

    public function testConstructorSetsOwnerAndPendingStatus(): void
    {
        $owner = new User('owner@example.com', 'Owner');

        $import = new TakeoutImport($owner);

        $this->assertSame($owner, $import->getOwner());
        $this->assertSame(TakeoutImport::STATUS_PENDING, $import->getStatus());
    }

    public function testConstructorSetsCreatedAtToNow(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $before = new \DateTimeImmutable();

        $import = new TakeoutImport($owner);

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $import->getCreatedAt());
        $this->assertLessThanOrEqual($after, $import->getCreatedAt());
    }

    public function testConstructorInitializesCountersToNull(): void
    {
        $owner = new User('owner@example.com', 'Owner');

        $import = new TakeoutImport($owner);

        $this->assertNull($import->getMediaImportedCount());
        $this->assertNull($import->getDuplicatesSkippedCount());
        $this->assertNull($import->getUnrecognizedFilesCount());
        $this->assertNull($import->getCompletedAt());
        $this->assertNull($import->getErrorMessage());
    }

    public function testMarkExtractingUpdatesStatus(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markExtracting();

        $this->assertSame(TakeoutImport::STATUS_EXTRACTING, $import->getStatus());
    }

    public function testMarkProcessingUpdatesStatus(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markProcessing();

        $this->assertSame(TakeoutImport::STATUS_PROCESSING, $import->getStatus());
    }

    public function testMarkCompletedSetsStatusCountersAndCompletedAt(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));
        $before = new \DateTimeImmutable();

        $import->markCompleted(mediaImported: 120, duplicatesSkipped: 5, unrecognizedFiles: 2);

        $after = new \DateTimeImmutable();

        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $import->getStatus());
        $this->assertSame(120, $import->getMediaImportedCount());
        $this->assertSame(5, $import->getDuplicatesSkippedCount());
        $this->assertSame(2, $import->getUnrecognizedFilesCount());
        $this->assertGreaterThanOrEqual($before, $import->getCompletedAt());
        $this->assertLessThanOrEqual($after, $import->getCompletedAt());
    }

    public function testMarkFailedSetsStatusErrorMessageAndPartialCounters(): void
    {
        // Gestion d'échec partiel (tranché, plan-327-takeout-import.md) :
        // pas de rollback — les compteurs reflètent ce qui a réellement été
        // importé jusqu'au crash, pas zéro.
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markFailed('Disque plein après 500 fichiers', mediaImported: 500, duplicatesSkipped: 3, unrecognizedFiles: 0);

        $this->assertSame(TakeoutImport::STATUS_FAILED, $import->getStatus());
        $this->assertSame('Disque plein après 500 fichiers', $import->getErrorMessage());
        $this->assertSame(500, $import->getMediaImportedCount());
        $this->assertSame(3, $import->getDuplicatesSkippedCount());
        $this->assertSame(0, $import->getUnrecognizedFilesCount());
        $this->assertNotNull($import->getCompletedAt());
    }
}
