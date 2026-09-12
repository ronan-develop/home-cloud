<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ActivityTrackerInterface;

/**
 * Trace la dernière activité authentifiée sur l'instance courante, dans un
 * simple fichier plat (pas de DB, pas de process PHP à lancer pour le lire
 * depuis un futur script bash — cf. plan #422, contrainte LVE #395/#396).
 */
final readonly class ActivityTracker implements ActivityTrackerInterface
{
    private const AMORTIZATION_SECONDS = 300;

    public function __construct(
        private string $filePath,
    ) {}

    public function recordActivity(): void
    {
        $last = $this->getLastActivityAt();
        if ($last !== null && (time() - $last->getTimestamp()) < self::AMORTIZATION_SECONDS) {
            return;
        }

        $tmpPath = $this->filePath . '.tmp.' . getmypid();
        if (@file_put_contents($tmpPath, (string) time()) === false) {
            return;
        }

        if (!@rename($tmpPath, $this->filePath)) {
            @unlink($tmpPath);
        }
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        if (!is_file($this->filePath)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($this->filePath));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp((int) $raw);
    }
}
