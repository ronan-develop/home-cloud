<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\UploadBatch;
use App\Entity\User;
use App\Handler\MediaProcessHandler;
use App\Interface\MediaProcessorInterface;
use App\Interface\UploadBatchRepositoryInterface;
use App\Message\MediaProcessMessage;
use App\Repository\FileRepository;
use App\Interface\BatchCompletionNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class MediaProcessHandlerTest extends TestCase
{
    private function handler(
        FileRepository $fileRepo,
        MediaProcessorInterface $processor,
        ?UploadBatchRepositoryInterface $batchRepo = null,
        ?BatchCompletionNotifierInterface $notifier = null,
        ?EntityManagerInterface $em = null,
        ?LoggerInterface $logger = null,
    ): MediaProcessHandler {
        return new MediaProcessHandler(
            $fileRepo,
            $processor,
            $batchRepo ?? $this->createStub(UploadBatchRepositoryInterface::class),
            $notifier ?? $this->createStub(BatchCompletionNotifierInterface::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testHandlerDelegatesToMediaProcessorWhenFileExists(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getBatch')->willReturn(null);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $processor = $this->createMock(MediaProcessorInterface::class);
        $processor->expects($this->once())->method('process')->with($file);

        $this->handler($fileRepo, $processor)(new MediaProcessMessage('some-uuid'));
    }

    public function testHandlerDoesNothingWhenFileNotFound(): void
    {
        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn(null);

        $processor = $this->createMock(MediaProcessorInterface::class);
        $processor->expects($this->never())->method('process');

        $this->handler($fileRepo, $processor)(new MediaProcessMessage('some-uuid'));
    }

    /**
     * Dernier fichier du lot traité → le lot est marqué terminé et le
     * propriétaire est notifié, une seule fois.
     */
    public function testNotifiesWhenLastFileOfDeferredBatchIsProcessed(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $batch = new UploadBatch($owner, 2, 300_000_000, UploadBatch::MODE_DEFERRED);

        $file = $this->createStub(File::class);
        $file->method('getBatch')->willReturn($batch);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $processor = $this->createStub(MediaProcessorInterface::class);

        $batchRepo = $this->createStub(UploadBatchRepositoryInterface::class);
        $batchRepo->method('countProcessed')->willReturn(2); // tous traités

        $notifier = $this->createMock(BatchCompletionNotifierInterface::class);
        $notifier->expects($this->once())->method('notify')->with($batch);

        $em = $this->createStub(EntityManagerInterface::class);

        $this->handler($fileRepo, $processor, $batchRepo, $notifier, $em)(new MediaProcessMessage('id'));

        $this->assertSame(UploadBatch::STATUS_COMPLETED, $batch->getStatus());
        $this->assertNotNull($batch->getCompletedAt());
    }

    /**
     * Fichier intermédiaire (le lot n'est pas complet) → aucune notification.
     */
    public function testDoesNotNotifyWhenBatchStillIncomplete(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $batch = new UploadBatch($owner, 3, 300_000_000, UploadBatch::MODE_DEFERRED);

        $file = $this->createStub(File::class);
        $file->method('getBatch')->willReturn($batch);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $batchRepo = $this->createStub(UploadBatchRepositoryInterface::class);
        $batchRepo->method('countProcessed')->willReturn(1); // 1/3

        $notifier = $this->createMock(BatchCompletionNotifierInterface::class);
        $notifier->expects($this->never())->method('notify');

        $this->handler(
            $fileRepo,
            $this->createStub(MediaProcessorInterface::class),
            $batchRepo,
            $notifier,
        )(new MediaProcessMessage('id'));

        $this->assertNotSame(UploadBatch::STATUS_COMPLETED, $batch->getStatus());
    }

    /**
     * Idempotence : un lot déjà notifié (notifiedAt posé) ne renvoie jamais un
     * second email, même si le worker rejoue un message.
     */
    public function testDoesNotNotifyTwiceForAlreadyNotifiedBatch(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $batch = new UploadBatch($owner, 1, 300_000_000, UploadBatch::MODE_DEFERRED);
        $batch->setNotifiedAt(new \DateTimeImmutable('-1 minute'));

        $file = $this->createStub(File::class);
        $file->method('getBatch')->willReturn($batch);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $batchRepo = $this->createStub(UploadBatchRepositoryInterface::class);
        $batchRepo->method('countProcessed')->willReturn(1);

        $notifier = $this->createMock(BatchCompletionNotifierInterface::class);
        $notifier->expects($this->never())->method('notify');

        $this->handler(
            $fileRepo,
            $this->createStub(MediaProcessorInterface::class),
            $batchRepo,
            $notifier,
        )(new MediaProcessMessage('id'));
    }

    /**
     * Un lot immediate ne passe jamais par le worker pour la notification :
     * même traité ici (secours), il ne déclenche pas d'email.
     */
    public function testImmediateBatchIsNeverNotified(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $batch = new UploadBatch($owner, 1, 500_000, UploadBatch::MODE_IMMEDIATE);

        $file = $this->createStub(File::class);
        $file->method('getBatch')->willReturn($batch);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $batchRepo = $this->createStub(UploadBatchRepositoryInterface::class);
        $batchRepo->method('countProcessed')->willReturn(1);

        $notifier = $this->createMock(BatchCompletionNotifierInterface::class);
        $notifier->expects($this->never())->method('notify');

        $this->handler(
            $fileRepo,
            $this->createStub(MediaProcessorInterface::class),
            $batchRepo,
            $notifier,
        )(new MediaProcessMessage('id'));
    }

    /**
     * Un Media créé sans vignette (thumbnailPath null) est un succès partiel :
     * on veut le voir dans les logs pour distinguer "traité mais dégradé" de
     * "jamais traité" — observé en prod sur #312, impossible à diagnostiquer
     * sans ce niveau de détail.
     */
    public function testLogsInfoWithFileIdWhenMediaCreatedWithoutThumbnail(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::fromString('0195c2f0-0000-7000-8000-000000000001'));
        $file->method('getOriginalName')->willReturn('video.mp4');
        $file->method('getBatch')->willReturn(null);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $media = $this->createStub(Media::class);
        $media->method('getThumbnailPath')->willReturn(null);

        $processor = $this->createStub(MediaProcessorInterface::class);
        $processor->method('process')->willReturn($media);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('sans vignette'),
                $this->callback(fn (array $context) => ($context['fileName'] ?? null) === 'video.mp4'),
            );

        $this->handler($fileRepo, $processor, logger: $logger)(new MediaProcessMessage('0195c2f0-0000-7000-8000-000000000001'));
    }

    /**
     * Un Media créé avec vignette : succès complet, tracé en info (pas warning).
     */
    public function testLogsInfoWithFileIdWhenMediaCreatedWithThumbnail(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::fromString('0195c2f0-0000-7000-8000-000000000002'));
        $file->method('getOriginalName')->willReturn('photo.jpg');
        $file->method('getBatch')->willReturn(null);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $media = $this->createStub(Media::class);
        $media->method('getThumbnailPath')->willReturn('thumbs/abc.jpg');

        $processor = $this->createStub(MediaProcessorInterface::class);
        $processor->method('process')->willReturn($media);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('traité'),
                $this->callback(fn (array $context) => ($context['fileName'] ?? null) === 'photo.jpg'),
            );

        $this->handler($fileRepo, $processor, logger: $logger)(new MediaProcessMessage('0195c2f0-0000-7000-8000-000000000002'));
    }

    /**
     * MediaProcessor::process() renvoie null pour un type non pris en charge
     * (ex: PDF) : ne doit jamais être confondu avec "Media traité" (succès).
     */
    public function testLogsInfoWhenFileTypeHasNoAssociatedMedia(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::fromString('0195c2f0-0000-7000-8000-000000000003'));
        $file->method('getOriginalName')->willReturn('document.pdf');
        $file->method('getBatch')->willReturn(null);

        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $processor = $this->createStub(MediaProcessorInterface::class);
        $processor->method('process')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('non pris en charge'),
                $this->callback(fn (array $context) => ($context['fileName'] ?? null) === 'document.pdf'),
            );

        $this->handler($fileRepo, $processor, logger: $logger)(new MediaProcessMessage('0195c2f0-0000-7000-8000-000000000003'));
    }

    /**
     * Fichier introuvable en base : traçé pour distinguer d'un traitement
     * silencieusement sauté par accident (message rejoué après suppression).
     */
    public function testLogsWarningWhenFileNotFound(): void
    {
        $fileRepo = $this->createStub(FileRepository::class);
        $fileRepo->method('find')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('introuvable'),
                $this->callback(fn (array $context) => ($context['fileId'] ?? null) === 'missing-uuid'),
            );

        $this->handler($fileRepo, $this->createStub(MediaProcessorInterface::class), logger: $logger)(
            new MediaProcessMessage('missing-uuid'),
        );
    }
}
