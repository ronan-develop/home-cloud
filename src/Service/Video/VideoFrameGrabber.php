<?php

declare(strict_types=1);

namespace App\Service\Video;

use App\Exception\Video\FrameExtractionFailedException;
use App\Exception\Video\VideoToolUnavailableException;
use App\Interface\VideoFrameGrabberInterface;
use Symfony\Component\Process\Process;

/**
 * Extrait une frame via un binaire ffmpeg statique.
 *
 * o2switch (mutualisé, pas de root) n'a ni ffmpeg système ni imagick : le
 * binaire est déposé dans var/bin/ par bin/install-ffmpeg.sh au déploiement.
 *
 * La commande est construite en tableau d'arguments : un nom de fichier
 * contenant des métacaractères shell est passé tel quel à execve(), jamais
 * réinterprété par un shell.
 */
final class VideoFrameGrabber implements VideoFrameGrabberInterface
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly string $ffmpegBin) {}

    public function grab(string $absolutePath, float $seekSeconds): ExtractedVideoFrame
    {
        if (!is_executable($this->ffmpegBin)) {
            throw new VideoToolUnavailableException(
                sprintf('ffmpeg introuvable ou non exécutable : %s', $this->ffmpegBin),
            );
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'hc-video-thumb-').'.jpg';

        try {
            $process = new Process([
                $this->ffmpegBin,
                '-ss', (string) $seekSeconds,
                '-i', $absolutePath,
                '-frames:v', '1',
                '-q:v', '2',
                '-f', 'image2',
                '-y',
                $outputPath,
            ]);
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new FrameExtractionFailedException(
                    'ffmpeg a échoué : '.$process->getErrorOutput(),
                );
            }

            $jpegData = @file_get_contents($outputPath);
            if ($jpegData === false || $jpegData === '') {
                throw new FrameExtractionFailedException("ffmpeg n'a produit aucune image");
            }

            return new ExtractedVideoFrame($jpegData);
        } finally {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
        }
    }
}
