<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BroadcastMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Message admin diffusé en in-app (#361, suite de #283).
 *
 * Un seul message actif à la fois par instance : la ligne la plus récente
 * (createdAt DESC) fait foi. Comparé à User::lastBroadcastSeenAt pour
 * déterminer lu/non-lu — pas de suppression automatique après lecture.
 */
#[ORM\Entity(repositoryClass: BroadcastMessageRepository::class)]
#[ORM\Table(name: 'broadcast_messages')]
class BroadcastMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $subject, string $body)
    {
        $this->id = Uuid::v7();
        $this->subject = $subject;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
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
}
