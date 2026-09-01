<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DirectMessage;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class DirectMessageTest extends TestCase
{
    public function testConstructionSetsAllFieldsAndGeneratesUuidV7(): void
    {
        $sender = $this->createStub(User::class);
        $recipient = $this->createStub(User::class);

        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps du message');

        $this->assertInstanceOf(UuidV7::class, $message->getId());
        $this->assertSame($sender, $message->getSender());
        $this->assertSame($recipient, $message->getRecipient());
        $this->assertSame('Sujet', $message->getSubject());
        $this->assertSame('Corps du message', $message->getBody());
        $this->assertNull($message->getReadAt());
        $this->assertFalse($message->isRead());
    }

    public function testMarkAsReadSetsReadAt(): void
    {
        $sender = $this->createStub(User::class);
        $recipient = $this->createStub(User::class);

        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps');
        $message->markAsRead();

        $this->assertInstanceOf(\DateTimeImmutable::class, $message->getReadAt());
        $this->assertTrue($message->isRead());
    }

    public function testMarkAsReadIsIdempotent(): void
    {
        $sender = $this->createStub(User::class);
        $recipient = $this->createStub(User::class);

        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps');
        $message->markAsRead();
        $firstReadAt = $message->getReadAt();

        $message->markAsRead();

        $this->assertSame($firstReadAt, $message->getReadAt());
    }
}
