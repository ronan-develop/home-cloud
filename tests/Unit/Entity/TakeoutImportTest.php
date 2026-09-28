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

    // #522 : traiter les imports Takeout la nuit pour limiter la contention
    // sur le mutualisé o2switch (le worker était tué en pleine journée sous
    // forte charge, même sur un import découpé par ZIP, #520). markScheduled()
    // marque un import complet (tous les ZIP uploadés) comme prêt à démarrer
    // au prochain cycle nocturne, distinct de "pending" (encore en cours
    // d'upload) pour ne pas casser la logique de reprise existante (#491).
    public function testMarkScheduledUpdatesStatus(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markScheduled();

        $this->assertSame(TakeoutImport::STATUS_SCHEDULED, $import->getStatus());
    }

    public function testMarkExtractingUpdatesStatus(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markExtracting();

        $this->assertSame(TakeoutImport::STATUS_EXTRACTING, $import->getStatus());
    }

    // #515 : progression pendant l'extraction — jusqu'ici aucune progression
    // n'était communiquée pendant toute la phase "extracting", potentiellement
    // plusieurs minutes sur des ZIP volumineux. markExtracting() reçoit
    // désormais le nombre total de ZIP à extraire, et extractedZipCount est
    // incrémenté au fil du handler (même pattern que processedCount/totalMediaCount).
    public function testMarkExtractingSetsTotalZipCount(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markExtracting(totalZipCount: 11);

        $this->assertSame(11, $import->getTotalZipCount());
    }

    public function testConstructorInitializesExtractedZipCountToZeroAndTotalToNull(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $this->assertSame(0, $import->getExtractedZipCount());
        $this->assertNull($import->getTotalZipCount());
    }

    public function testIncrementExtractedZipCountIncrementsByOne(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->incrementExtractedZipCount();
        $import->incrementExtractedZipCount();

        $this->assertSame(2, $import->getExtractedZipCount());
    }

    public function testMarkProcessingUpdatesStatus(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markProcessing();

        $this->assertSame(TakeoutImport::STATUS_PROCESSING, $import->getStatus());
    }

    /**
     * Progress bar (#327) : le total de médias détectés n'est connu qu'après
     * le parsing de l'arborescence extraite — markProcessing() le reçoit
     * pour que le front puisse calculer processedCount/totalMediaCount.
     */
    public function testMarkProcessingSetsTotalMediaCount(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->markProcessing(totalMediaCount: 250);

        $this->assertSame(250, $import->getTotalMediaCount());
    }

    public function testConstructorInitializesProcessedCountToZeroAndTotalToNull(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $this->assertSame(0, $import->getProcessedCount());
        $this->assertNull($import->getTotalMediaCount());
    }

    public function testIncrementProcessedCountIncrementsByOne(): void
    {
        $import = new TakeoutImport(new User('owner@example.com', 'Owner'));

        $import->incrementProcessedCount();
        $import->incrementProcessedCount();

        $this->assertSame(2, $import->getProcessedCount());
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
