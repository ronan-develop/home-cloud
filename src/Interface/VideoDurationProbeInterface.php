<?php

declare(strict_types=1);

namespace App\Interface;

interface VideoDurationProbeInterface
{
    /**
     * Obtient la durée d'une vidéo en secondes.
     *
     * Ne lève jamais : une durée inconnue n'est pas un échec, l'appelant
     * retombe sur la première frame.
     */
    public function durationInSeconds(string $absolutePath): ?float;
}
