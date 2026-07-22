<?php

declare(strict_types=1);

namespace App\Service\Video;

use App\Interface\VideoDurationProbeInterface;
use App\Interface\VideoFrameGrabberInterface;
use App\Interface\VideoThumbnailExtractorInterface;

/**
 * Façade : décide où viser, délègue l'extraction.
 *
 * N'exécute elle-même aucun binaire — elle orchestre la sonde et le grabber.
 */
final class FfmpegVideoThumbnailExtractor implements VideoThumbnailExtractorInterface
{
    private const SUPPORTED_EXTENSIONS = ['mp4', 'webm', 'mov'];

    public function __construct(
        private readonly VideoDurationProbeInterface $durationProbe,
        private readonly VideoFrameGrabberInterface $frameGrabber,
    ) {}

    public function supports(string $absolutePath): bool
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    public function extract(string $absolutePath): ExtractedVideoFrame
    {
        // Durée inconnue (stream sans métadonnée, ffprobe absent) : on vise la
        // première frame. Moins représentative, mais mieux que rien.
        $duration = $this->durationProbe->durationInSeconds($absolutePath);
        $seek = $duration !== null ? $duration / 2 : 0.0;

        return $this->frameGrabber->grab($absolutePath, $seek);
    }

    /**
     * Extracteur câblé avec les collaborateurs standards.
     *
     * Raccourci pour les tests et scripts sans conteneur — sous Symfony,
     * c'est services.yaml qui fait foi.
     */
    public static function createDefault(string $ffmpegBin, string $ffprobeBin): self
    {
        return new self(
            new VideoDurationProbe($ffprobeBin),
            new VideoFrameGrabber($ffmpegBin),
        );
    }
}
