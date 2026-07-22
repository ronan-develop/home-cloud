<?php

declare(strict_types=1);

namespace App\Service\Video;

use App\Interface\VideoDurationProbeInterface;
use Symfony\Component\Process\Process;

/**
 * Durée d'une vidéo via ffprobe.
 *
 * Ne lève jamais : une durée introuvable fait viser la première frame, ce
 * qui reste préférable à l'absence de vignette.
 */
final class VideoDurationProbe implements VideoDurationProbeInterface
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly string $ffprobeBin) {}

    public function durationInSeconds(string $absolutePath): ?float
    {
        if (!is_executable($this->ffprobeBin)) {
            return null;
        }

        try {
            $process = new Process([
                $this->ffprobeBin,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $absolutePath,
            ]);
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $duration = (float) trim($process->getOutput());

            return $duration > 0 ? $duration : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
