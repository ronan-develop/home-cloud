<?php

declare(strict_types=1);

namespace App\Interface;

use App\Exception\Video\VideoThumbnailExtractionException;
use App\Service\Video\ExtractedVideoFrame;

interface VideoFrameGrabberInterface
{
    /**
     * Extrait une frame vidéo à un instant donné.
     *
     * Ne décide pas quel instant : c'est la façade qui calcule le seek.
     *
     * @throws VideoThumbnailExtractionException
     */
    public function grab(string $absolutePath, float $seekSeconds): ExtractedVideoFrame;
}
