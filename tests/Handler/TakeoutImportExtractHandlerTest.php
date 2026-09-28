<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Handler\TakeoutImportExtractHandler;
use App\Message\TakeoutImportExtractMessage;
use App\Message\TakeoutImportProcessMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutZipExtractor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * #520 : un ZIP à la fois — sur un import volumineux (plusieurs dizaines de
 * Go dans un seul message), le worker Messenger était tué en plein milieu de
 * l'extraction (contention LVE du mutualisé o2switch, constaté en conditions
 * réelles le 2026-09-28, aucune exception catchable côté PHP). Ce handler
 * n'extrait qu'un seul ZIP par appel, puis redispatche le reste ou passe la
 * main à TakeoutImportProcessMessage une fois tout extrait.
 */
final class TakeoutImportExtractHandlerTest extends TestCase
{
    private function makeImport(): TakeoutImport
    {
        return new TakeoutImport(new User('takeout-extract@example.com', 'Takeout'));
    }

    public function testExtractsOneZipIncrementsCountAndDispatchesNextZipWhenMoreRemain(): void
    {
        $import = $this->makeImport();

        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn($import);

        $extractor = $this->createMock(TakeoutZipExtractor::class);
        $extractor->expects($this->once())->method('extract')->with('/tmp/a.zip', $this->anything());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof TakeoutImportExtractMessage
                    && $message->zipPath === '/tmp/b.zip'
                    && $message->remainingZipPaths === [];
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        $handler(new TakeoutImportExtractMessage((string) $import->getId(), '/tmp/a.zip', ['/tmp/b.zip']));

        $this->assertSame(TakeoutImport::STATUS_EXTRACTING, $import->getStatus());
        $this->assertSame(1, $import->getExtractedZipCount());
    }

    // #515 (progress bar pendant l'extraction) : le premier appel du handler
    // doit fixer totalZipCount une fois pour toutes — déduit de
    // remainingZipPaths (le seul endroit qui connaît encore le compte total
    // à ce stade), pas recalculé à chaque ZIP.
    public function testFirstCallSetsTotalZipCountFromRemainingPathsPlusCurrent(): void
    {
        $import = $this->makeImport();

        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn($import);

        $extractor = $this->createStub(TakeoutZipExtractor::class);
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        $handler(new TakeoutImportExtractMessage((string) $import->getId(), '/tmp/a.zip', ['/tmp/b.zip', '/tmp/c.zip']));

        $this->assertSame(3, $import->getTotalZipCount());
    }

    // Un ZIP intermédiaire (ni le premier ni le dernier) ne doit pas
    // recalculer totalZipCount à partir de son propre remainingZipPaths (qui
    // ne reflète plus que ce qu'il reste, pas le total) — il doit rester
    // inchangé, déjà fixé par le premier appel.
    public function testSubsequentCallDoesNotOverwriteAlreadySetTotalZipCount(): void
    {
        $import = $this->makeImport();
        $import->markExtracting(3);

        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn($import);

        $extractor = $this->createStub(TakeoutZipExtractor::class);
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        // Deuxième ZIP du lot : il ne reste plus qu'un ZIP après lui, mais le
        // total réel (3) ne doit pas être écrasé par 1+1=2.
        $handler(new TakeoutImportExtractMessage((string) $import->getId(), '/tmp/b.zip', ['/tmp/c.zip']));

        $this->assertSame(3, $import->getTotalZipCount());
    }

    public function testDispatchesProcessMessageWhenLastZipExtracted(): void
    {
        $import = $this->makeImport();

        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn($import);

        $extractor = $this->createStub(TakeoutZipExtractor::class);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($import) {
                return $message instanceof TakeoutImportProcessMessage
                    && $message->takeoutImportId === (string) $import->getId();
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        $handler(new TakeoutImportExtractMessage((string) $import->getId(), '/tmp/last.zip', []));

        $this->assertSame(1, $import->getExtractedZipCount());
    }

    public function testDoesNothingWhenImportNotFound(): void
    {
        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn(null);

        $extractor = $this->createMock(TakeoutZipExtractor::class);
        $extractor->expects($this->never())->method('extract');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        $handler(new TakeoutImportExtractMessage('missing-id', '/tmp/a.zip', []));
    }

    public function testCatchesExtractionExceptionMarksImportFailedAndDoesNotDispatchFurther(): void
    {
        $import = $this->makeImport();

        $repository = $this->createStub(TakeoutImportRepository::class);
        $repository->method('find')->willReturn($import);

        $extractor = $this->createMock(TakeoutZipExtractor::class);
        $extractor->method('extract')->willThrowException(new \RuntimeException('ZIP corrompu'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new TakeoutImportExtractHandler($repository, $extractor, $bus, $em, new NullLogger());
        $handler(new TakeoutImportExtractMessage((string) $import->getId(), '/tmp/a.zip', ['/tmp/b.zip']));

        $this->assertSame(TakeoutImport::STATUS_FAILED, $import->getStatus());
    }
}
