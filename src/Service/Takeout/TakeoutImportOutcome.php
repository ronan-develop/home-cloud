<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Entity\Media;

/**
 * Résultat de l'import d'une entrée par TakeoutMediaImporter — permet au
 * Handler d'alimenter ses compteurs (médias importés / doublons ignorés)
 * sans avoir à connaître la logique de détection de doublon.
 */
final readonly class TakeoutImportOutcome
{
    private function __construct(
        public bool $isDuplicate,
        public ?Media $media,
    ) {}

    public static function duplicate(): self
    {
        return new self(true, null);
    }

    public static function imported(?Media $media): self
    {
        return new self(false, $media);
    }
}
