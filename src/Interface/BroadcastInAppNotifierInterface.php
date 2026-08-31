<?php

declare(strict_types=1);

namespace App\Interface;

interface BroadcastInAppNotifierInterface
{
    /**
     * Persiste un nouveau BroadcastMessage actif localement, remplaçant
     * fonctionnellement l'ancien (un seul message actif à la fois).
     * Pas d'effet en dry-run.
     */
    public function notify(string $subject, string $body, bool $dryRun): void;
}
