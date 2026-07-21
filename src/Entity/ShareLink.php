<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShareLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Lien de partage public : donne accès à une ressource sans compte HomeCloud,
 * via un secret porté par l'URL (selector + token), jamais par une session.
 *
 * Choix, distincts de Share (partage entre comptes, cf. src/Entity/Share.php) :
 * - lecture seule uniquement : pas de champ `permission`, le lien n'autorise
 *   jamais l'écriture.
 * - expiresAt nullable = permanent, choisi explicitement par l'owner à la
 *   création (cf. ShareLinkFactory) — pour un usage type livraison d'album
 *   à un client, où la révocation manuelle reste le seul moyen de couper
 *   l'accès. Ce n'est plus un défaut implicite comme pour Share : l'owner
 *   choisit une durée bornée (1/7/30 jours) ou "permanent" à chaque lien.
 * - selector (clair, indexé) + hashedToken (hash du secret) : si la base
 *   fuite, les liens ne sont pas rejouables. Le token en clair n'existe que
 *   dans l'URL envoyée, jamais en base (cf. ShareLinkTokenGenerator).
 */
#[ORM\Entity(repositoryClass: ShareLinkRepository::class)]
#[ORM\Table(name: 'share_links')]
class ShareLink
{
    /** Délai de grâce avant purge définitive d'un lien révoqué (cf. app:share-link:purge-revoked). */
    public const PURGE_AFTER_DAYS = 30;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** file | folder | album */
    #[ORM\Column(type: 'string', length: 10)]
    private string $resourceType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $resourceId;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $selector;

    #[ORM\Column(type: 'string', length: 64)]
    private string $hashedToken;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        User $owner,
        string $resourceType,
        Uuid $resourceId,
        string $selector,
        string $hashedToken,
        ?\DateTimeImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->selector = $selector;
        $this->hashedToken = $hashedToken;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): Uuid
    {
        return $this->resourceId;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function reactivate(): void
    {
        $this->revokedAt = null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null && !$this->isExpired();
    }

    /** Jours restants avant purge définitive, ou null si le lien n'est pas révoqué. */
    public function daysUntilPurge(): ?int
    {
        if ($this->revokedAt === null) {
            return null;
        }

        $purgeAt = $this->revokedAt->modify(sprintf('+%d days', self::PURGE_AFTER_DAYS));
        $secondsRemaining = $purgeAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();

        return max(0, (int) ceil($secondsRemaining / 86400));
    }

    /**
     * Comparaison à temps constant : évite qu'un attaquant déduise le token
     * correct octet par octet en mesurant le temps de réponse (timing attack).
     */
    public function verifyToken(string $token): bool
    {
        return hash_equals($this->hashedToken, hash('sha256', $token));
    }
}
