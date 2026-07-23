<?php

declare(strict_types=1);

namespace App\Interface;

interface BroadcastOrchestratorInterface
{
    /**
     * Diffuse un message admin (#283) sur toutes les instances (si
     * $targetInstance est null) ou une seule instance ciblée.
     *
     * @return array<string, bool> prénom d'instance => succès
     */
    public function dispatch(string $subject, string $body, ?string $targetInstance, bool $dryRun): array;
}
