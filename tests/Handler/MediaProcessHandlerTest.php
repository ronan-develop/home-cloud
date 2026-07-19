<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\File;
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

final class MediaProcessHandlerTest extends TestCase
{
    private function handler(
        FileRepository $fileRepo,
        MediaProcessorInterface $processor,
        ?UploadBatchRepositoryInterface $batchRepo = null,
        ?BatchCompletionNotifierInterface $notifier = null,
        ?EntityManagerInterface $em = null,
    ): MediaProcessHandler {
        return new MediaProcessHandler(
            $fileRepo,
            $processor,
            $batchRepo ?? $this->createStub(UploadBatchRepositoryInterface::class),
            $notifier ?? $this->createStub(BatchCompletionNotifierInterface::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
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
}
