<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Une entrée du transport Messenger "failed" (#377), résumée pour l'écran
 * admin de santé cron.
 */
final readonly class CronFailureItem
{
    public function __construct(
        public string $messageClass,
        public string $errorMessage,
        public ?\DateTimeImmutable $failedAt,
    ) {}
}
