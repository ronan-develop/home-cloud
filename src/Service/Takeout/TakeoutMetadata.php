<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Résultat du parsing d'un fichier <nom>.supplemental-metadata.json
 * Google Takeout — cf. TakeoutMetadataReader.
 */
final readonly class TakeoutMetadata
{
    public function __construct(
        public ?\DateTimeImmutable $takenAt,
        public ?float $latitude,
        public ?float $longitude,
    ) {}
}
