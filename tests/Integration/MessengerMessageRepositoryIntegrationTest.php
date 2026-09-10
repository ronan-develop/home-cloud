<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\MediaProcessMessage;
use App\Message\ShareNotificationMessage;
use App\Repository\MessengerMessageRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;

final class MessengerMessageRepositoryIntegrationTest extends KernelTestCase
{
    private Connection $connection;
    private MessengerMessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->connection->executeStatement('DELETE FROM messenger_messages');

        $this->repository = $container->get(MessengerMessageRepository::class);
    }

    public function testCountPendingMailMessagesReturnsZeroWhenTableEmpty(): void
    {
        $this->assertSame(0, $this->repository->countPendingMailMessages());
    }

    public function testCountPendingMailMessagesCountsOnlyShareNotificationInAsyncQueue(): void
    {
        $this->insertMessage('async', new ShareNotificationMessage('share-1', 'Album vacances'));
        $this->insertMessage('async', new MediaProcessMessage('file-1'));
        $this->insertMessage('failed', new ShareNotificationMessage('share-2', 'Album été'));

        $this->assertSame(1, $this->repository->countPendingMailMessages());
    }

    public function testCountPendingMailMessagesExcludesAlreadyDeliveredMessages(): void
    {
        $this->insertMessage('async', new ShareNotificationMessage('share-1', 'Album vacances'), delivered: true);

        $this->assertSame(0, $this->repository->countPendingMailMessages());
    }

    public function testFindOldestPendingMailMessageAgeReturnsNullWhenNoneQueued(): void
    {
        $this->assertNull($this->repository->findOldestPendingMailMessageAge());
    }

    public function testFindOldestPendingMailMessageAgeReturnsCreatedAtOfOldestPendingMessage(): void
    {
        $older = new \DateTimeImmutable('2026-09-01 10:00:00');
        $newer = new \DateTimeImmutable('2026-09-05 10:00:00');

        $this->insertMessage('async', new ShareNotificationMessage('share-1', 'Album vacances'), createdAt: $newer);
        $this->insertMessage('async', new ShareNotificationMessage('share-2', 'Album été'), createdAt: $older);

        $result = $this->repository->findOldestPendingMailMessageAge();

        $this->assertNotNull($result);
        $this->assertSame($older->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    public function testCountFailedMailMessagesCountsOnlyShareNotificationInFailedQueue(): void
    {
        $this->insertMessage('failed', new ShareNotificationMessage('share-1', 'Album vacances'));
        $this->insertMessage('failed', new MediaProcessMessage('file-1'));
        $this->insertMessage('async', new ShareNotificationMessage('share-2', 'Album été'));

        $this->assertSame(1, $this->repository->countFailedMailMessages());
    }

    private function insertMessage(
        string $queueName,
        object $message,
        ?\DateTimeImmutable $createdAt = null,
        bool $delivered = false,
    ): void {
        $envelope = new Envelope($message);
        $now = $createdAt ?? new \DateTimeImmutable();

        $this->connection->insert('messenger_messages', [
            'body' => serialize($envelope),
            'headers' => '[]',
            'queue_name' => $queueName,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'available_at' => $now->format('Y-m-d H:i:s'),
            'delivered_at' => $delivered ? $now->format('Y-m-d H:i:s') : null,
        ]);
    }
}
