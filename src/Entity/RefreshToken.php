<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité RefreshToken — token longue durée (7 jours) permettant d'obtenir
 * un nouveau JWT sans re-saisir les credentials.
 *
 * Rotation : à chaque appel /token/refresh, l'ancien token est invalidé
 * et un nouveau est émis (refresh token rotation).
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    /** TTL par défaut : 7 jours */
    public const TTL = 604800;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Token opaque 64 caractères hex (256 bits d'entropie) */
    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, ?\DateTimeImmutable $expiresAt = null)
    {
        $this->id = Uuid::v7();
        $this->token = bin2hex(random_bytes(32));
        $this->user = $user;
        $this->expiresAt = $expiresAt ?? new \DateTimeImmutable('+' . self::TTL . ' seconds');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }
}
