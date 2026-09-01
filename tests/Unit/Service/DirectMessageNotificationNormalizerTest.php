<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\NotificationItem;
use App\Entity\DirectMessage;
use App\Entity\User;
use App\Repository\DirectMessageRepository;
use App\Service\DirectMessageNotificationNormalizer;
use PHPUnit\Framework\TestCase;

final class DirectMessageNotificationNormalizerTest extends TestCase
{
    public function testNormalizesDirectMessagesForUser(): void
    {
        $sender = $this->createStub(User::class);
        $recipient = $this->createStub(User::class);
        $message = new DirectMessage($sender, $recipient, 'Sujet du message', 'Corps');

        $repository = $this->createStub(DirectMessageRepository::class);
        $repository->method('findForUser')->willReturn([$message]);

        $normalizer = new DirectMessageNotificationNormalizer($repository);

        $items = $normalizer->normalize($recipient);

        $this->assertCount(1, $items);
        $this->assertSame(NotificationItem::TYPE_DIRECT_MESSAGE, $items[0]->type);
        $this->assertSame('Sujet du message', $items[0]->title);
        $this->assertSame($message->getCreatedAt(), $items[0]->date);
        $this->assertSame('/direct-messages/' . $message->getId(), $items[0]->link);
        $this->assertSame($message->getId()->toString(), $items[0]->id);
        $this->assertFalse($items[0]->isRead);
    }

    public function testMarksItemAsReadWhenMessageIsRead(): void
    {
        $sender = $this->createStub(User::class);
        $recipient = $this->createStub(User::class);
        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps');
        $message->markAsRead();

        $repository = $this->createStub(DirectMessageRepository::class);
        $repository->method('findForUser')->willReturn([$message]);

        $normalizer = new DirectMessageNotificationNormalizer($repository);

        $items = $normalizer->normalize($recipient);

        $this->assertTrue($items[0]->isRead);
    }
}
