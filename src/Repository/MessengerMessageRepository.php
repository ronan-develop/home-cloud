<?php

declare(strict_types=1);

namespace App\Repository;

use App\Message\ShareNotificationMessage;
use Doctrine\DBAL\Connection;

/**
 * Lecture de la table Doctrine Messenger `messenger_messages` (pas de mapping
 * ORM — accès DBAL direct). Le body est sérialisé en PHP `serialize()` natif
 * (comportement par défaut de DoctrineTransport, pas de serializer custom
 * configuré) : le nom de classe du message n'apparaît pas dans `headers`
 * (toujours `[]`), il faut le chercher dans `body`.
 */
final readonly class MessengerMessageRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function countPendingMailMessages(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND body LIKE ? AND delivered_at IS NULL',
            ['async', $this->mailMessageLikePattern()],
        );
    }

    public function findOldestPendingMailMessageAge(): ?\DateTimeImmutable
    {
        $oldest = $this->connection->fetchOne(
            'SELECT MIN(created_at) FROM messenger_messages WHERE queue_name = ? AND body LIKE ? AND delivered_at IS NULL',
            ['async', $this->mailMessageLikePattern()],
        );

        return $oldest === null || $oldest === false
            ? null
            : new \DateTimeImmutable($oldest);
    }

    public function countFailedMailMessages(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND body LIKE ?',
            ['failed', $this->mailMessageLikePattern()],
        );
    }

    private function mailMessageLikePattern(): string
    {
        return '%' . addcslashes(ShareNotificationMessage::class, '\\') . '%';
    }
}
