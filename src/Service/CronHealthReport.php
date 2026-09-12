<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rapport de santé du cron Messenger de l'instance courante (#377).
 */
final readonly class CronHealthReport
{
    /**
     * @param CronFailureItem[] $recentFailures
     */
    public function __construct(
        public int $failedCount,
        public array $recentFailures,
    ) {}
}
