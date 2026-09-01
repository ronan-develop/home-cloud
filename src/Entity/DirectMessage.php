<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DirectMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Message ciblé 1-à-1 envoyé par l'admin whitelisté (AdminVoter) à un
 * utilisateur précis (#373) — distinct du broadcast (#283), qui n'a pas de
 * destinataire individuel ni de persistance in-app.
 *
 * Historique multi-messages : contrairement à Share/ShareLink, il n'y a pas
 * de notion de révocation, seulement un readAt nullable marquant la lecture.
 */
#[ORM\Entity(repositoryClass: DirectMessageRepository::class)]
#[ORM\Table(name: 'direct_messages')]
#[ORM\Index(name: 'idx_direct_message_recipient_unread', columns: ['recipient_id', 'read_at'])]
class DirectMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(User $sender, User $recipient, string $subject, string $body)
    {
        $this->id = Uuid::v7();
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->subject = $subject;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getSender(): User
    {
        return $this->sender;
    }
    public function getRecipient(): User
    {
        return $this->recipient;
    }
    public function getSubject(): string
    {
        return $this->subject;
    }
    public function getBody(): string
    {
        return $this->body;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markAsRead(): void
    {
        if ($this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
        }
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
