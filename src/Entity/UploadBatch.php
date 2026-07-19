<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UploadBatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Regroupe les fichiers d'un même envoi (multi-upload) pour permettre au serveur
 * de raisonner sur le lot entier, alors que chaque fichier part dans sa propre
 * requête HTTP (le front génère un batchId partagé).
 *
 * Rôle :
 * - porter la décision de routage (`mode`) prise à la création par
 *   UploadRoutingDecider : `immediate` (traitement sur kernel.terminate) ou
 *   `deferred` (déport au worker Messenger pour les lots lourds) ;
 * - servir de point d'agrégation pour la fin de traitement (comptage des Media
 *   créés) et la notification unique (email + toast), gérés dans les lots suivants.
 *
 * `notifiedAt` garantit qu'un lot n'est notifié qu'une fois même si le worker
 * rejoue un message (idempotence).
 */
#[ORM\Entity(repositoryClass: UploadBatchRepository::class)]
#[ORM\Table(name: 'upload_batches')]
class UploadBatch
{
    public const MODE_IMMEDIATE = 'immediate';
    public const MODE_DEFERRED = 'deferred';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    /** Nombre de fichiers annoncés dans le lot. */
    #[ORM\Column]
    private int $expectedCount;

    /**
     * Taille cumulée annoncée, en octets. `bigint` (donc string côté Doctrine)
     * car un lot lourd — le cas d'usage du worker — dépasse volontiers la limite
     * d'un INTEGER 32 bits (~2 Go).
     */
    #[ORM\Column(type: Types::BIGINT)]
    private string $totalSize;

    #[ORM\Column(length: 16)]
    private string $mode;

    #[ORM\Column(length: 16, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct(User $owner, int $expectedCount, int $totalSize, string $mode)
    {
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'test') {
            // UUID v4 aléatoire garanti unique (même contrainte qu'App\Entity\File).
            $this->id = Uuid::fromString(
                sprintf(
                    '%08x-%04x-%04x-%04x-%012x',
                    random_int(0, 0xffffffff),
                    random_int(0, 0xffff),
                    (random_int(0, 0x0fff) | 0x4000),
                    (random_int(0, 0x3fff) | 0x8000),
                    random_int(0, 0xffffffffffff)
                )
            );
        } else {
            $this->id = Uuid::v7();
        }

        $this->owner = $owner;
        $this->expectedCount = $expectedCount;
        $this->totalSize = (string) $totalSize;
        $this->mode = $mode;
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

    public function getExpectedCount(): int
    {
        return $this->expectedCount;
    }

    public function getTotalSize(): int
    {
        return (int) $this->totalSize;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isDeferred(): bool
    {
        return $this->mode === self::MODE_DEFERRED;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): void
    {
        $this->notifiedAt = $notifiedAt;
    }
}
