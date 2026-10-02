<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TakeoutImportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Suivi d'un import Google Photos Takeout (#327).
 *
 * Distinct d'UploadBatch : le nombre de médias n'est connu qu'après dézip
 * (pas à l'upload comme pour un lot multi-fichiers classique), d'où un
 * cycle de statuts propre (pending → extracting → processing → completed/failed).
 *
 * Pas de rollback en cas d'échec partiel (tranché, plan-327-takeout-import.md) :
 * les compteurs reflètent ce qui a réellement été importé jusqu'au crash,
 * jamais remis à zéro.
 */
#[ORM\Entity(repositoryClass: TakeoutImportRepository::class)]
#[ORM\Table(name: 'takeout_imports')]
class TakeoutImport
{
    public const STATUS_PENDING = 'pending';
    // #522 : import complet (tous les ZIP uploadés), en attente du prochain
    // cycle nocturne avant de démarrer l'extraction — distinct de "pending"
    // (encore en cours d'upload, cf. reprise #491) pour ne pas confondre les
    // deux dans TakeoutImportFindPendingController.
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXTRACTING = 'extracting';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    // #493 : délai avant purge automatique d'un import pending jamais repris
    // (fichiers déjà uploadés + ligne base) — compromis entre libérer
    // l'espace disque (ZIP Takeout volumineux) et laisser une marge
    // confortable pour reprendre après une pause de plusieurs jours.
    public const PURGE_AFTER_DAYS = 7;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 16)]
    private string $status;

    #[ORM\Column(nullable: true)]
    private ?int $mediaImportedCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $duplicatesSkippedCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $unrecognizedFilesCount = null;

    /**
     * Progress bar (#327) : total de médias détectés après le parsing de
     * l'arborescence extraite — null tant que le statut n'a pas atteint
     * "processing" (le total n'est connu qu'à ce moment).
     */
    #[ORM\Column(nullable: true)]
    private ?int $totalMediaCount = null;

    /**
     * Progress bar (#327) : nombre de médias déjà traités (importés ou
     * doublons confondus) — incrémenté au fil du Handler, jamais null
     * (contrairement aux compteurs finaux qui restent null tant que
     * l'import n'est pas terminé).
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $processedCount = 0;

    /**
     * Progress bar pendant l'extraction (#515) : nombre de ZIP à extraire,
     * connu dès le statut "extracting" — contrairement à totalMediaCount qui
     * n'est connu qu'après le parsing.
     */
    #[ORM\Column(nullable: true)]
    private ?int $totalZipCount = null;

    /**
     * Progress bar pendant l'extraction (#515) : nombre de ZIP déjà extraits
     * — incrémenté au fil du Handler, même pattern que processedCount.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $extractedZipCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Posée par markScheduled() (#528) : permet d'afficher depuis quand un
     * import attend un créneau calme. Null pour les imports passés en
     * scheduled avant cette colonne, et pour tout autre statut.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(User $owner)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->status = self::STATUS_PENDING;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMediaImportedCount(): ?int
    {
        return $this->mediaImportedCount;
    }

    public function getDuplicatesSkippedCount(): ?int
    {
        return $this->duplicatesSkippedCount;
    }

    public function getUnrecognizedFilesCount(): ?int
    {
        return $this->unrecognizedFilesCount;
    }

    public function getTotalMediaCount(): ?int
    {
        return $this->totalMediaCount;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function incrementProcessedCount(): void
    {
        ++$this->processedCount;
    }

    public function getTotalZipCount(): ?int
    {
        return $this->totalZipCount;
    }

    public function getExtractedZipCount(): int
    {
        return $this->extractedZipCount;
    }

    public function incrementExtractedZipCount(): void
    {
        ++$this->extractedZipCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markScheduled(): void
    {
        $this->status = self::STATUS_SCHEDULED;
        $this->scheduledAt = new \DateTimeImmutable();
    }

    public function markExtracting(?int $totalZipCount = null): void
    {
        $this->status = self::STATUS_EXTRACTING;
        $this->totalZipCount = $totalZipCount;
    }

    public function markProcessing(?int $totalMediaCount = null): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->totalMediaCount = $totalMediaCount;
    }

    public function markCompleted(int $mediaImported, int $duplicatesSkipped, int $unrecognizedFiles): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->mediaImportedCount = $mediaImported;
        $this->duplicatesSkippedCount = $duplicatesSkipped;
        $this->unrecognizedFilesCount = $unrecognizedFiles;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Pas de rollback (tranché) : les compteurs passés ici reflètent ce qui
     * a réellement été importé jusqu'au crash, jamais zéro par défaut.
     */
    public function markFailed(string $errorMessage, int $mediaImported, int $duplicatesSkipped, int $unrecognizedFiles): void
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
        $this->mediaImportedCount = $mediaImported;
        $this->duplicatesSkippedCount = $duplicatesSkipped;
        $this->unrecognizedFilesCount = $unrecognizedFiles;
        $this->completedAt = new \DateTimeImmutable();
    }
}
