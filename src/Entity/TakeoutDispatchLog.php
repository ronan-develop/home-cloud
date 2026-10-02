<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TakeoutDispatchLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Une ligne = un cycle de la commande app:takeout:nightly-dispatch (#528),
 * toutes les 15 min par instance. Alimente la page admin (efficacité du
 * dispatch dans le temps) et l'échantillonnage de charge de #547 : les
 * cycles "idle" (rien en attente) sont volontairement tracés aussi, sinon
 * aucune mesure de charge hors imports. Purgée au-delà de PURGE_AFTER_DAYS.
 */
#[ORM\Entity(repositoryClass: TakeoutDispatchLogRepository::class)]
#[ORM\Table(name: 'takeout_dispatch_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_takeout_dispatch_log_created_at')]
class TakeoutDispatchLog
{
    /** Serveur calme, imports en attente dispatchés. */
    public const OUTCOME_DISPATCHED = 'dispatched';
    /** Serveur trop chargé (ou mesure indisponible), dispatch reporté. */
    public const OUTCOME_DEFERRED = 'deferred';
    /** Aucun import en attente (serveur calme) : simple échantillon de charge. */
    public const OUTCOME_IDLE = 'idle';

    public const OUTCOMES = [self::OUTCOME_DISPATCHED, self::OUTCOME_DEFERRED, self::OUTCOME_IDLE];

    public const PURGE_AFTER_DAYS = 30;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 16)]
    private string $outcome;

    #[ORM\Column(nullable: true)]
    private ?float $load1;

    #[ORM\Column(nullable: true)]
    private ?float $load5;

    #[ORM\Column(nullable: true)]
    private ?float $load15;

    #[ORM\Column]
    private float $threshold;

    #[ORM\Column]
    private int $scheduledCount;

    #[ORM\Column]
    private int $dispatchedCount;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array{0: float, 1: float, 2: float}|null $loadAverage null si la mesure était indisponible
     */
    public function __construct(string $outcome, ?array $loadAverage, float $threshold, int $scheduledCount, int $dispatchedCount)
    {
        $this->id = Uuid::v7();
        $this->outcome = $outcome;
        $this->load1 = $loadAverage[0] ?? null;
        $this->load5 = $loadAverage[1] ?? null;
        $this->load15 = $loadAverage[2] ?? null;
        $this->threshold = $threshold;
        $this->scheduledCount = $scheduledCount;
        $this->dispatchedCount = $dispatchedCount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null
     */
    public function getLoadAverage(): ?array
    {
        if ($this->load1 === null || $this->load5 === null || $this->load15 === null) {
            return null;
        }

        return [$this->load1, $this->load5, $this->load15];
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getScheduledCount(): int
    {
        return $this->scheduledCount;
    }

    public function getDispatchedCount(): int
    {
        return $this->dispatchedCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
