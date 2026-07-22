<?php

declare(strict_types=1);

namespace App\Interface;

use App\Exception\Video\VideoThumbnailExtractionException;
use App\Service\Video\ExtractedVideoFrame;

interface VideoThumbnailExtractorInterface
{
    /**
     * Ce fichier est-il une vidéo dont on sait extraire une frame ?
     *
     * Décidé sur l'extension seule, sans toucher au disque — formats retenus
     * par #312 : mp4, webm, mov. Ne lève jamais.
     */
    public function supports(string $absolutePath): bool;

    /**
     * Extrait une frame représentative de la vidéo.
     *
     * @throws VideoThumbnailExtractionException toute cause d'échec ; un
     *                                           `catch` unique suffit
     */
    public function extract(string $absolutePath): ExtractedVideoFrame;
}
