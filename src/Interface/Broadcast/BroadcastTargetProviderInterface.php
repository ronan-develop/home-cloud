<?php

declare(strict_types=1);

namespace App\Interface\Broadcast;

interface BroadcastTargetProviderInterface
{
    /**
     * @return array<string, string> prénom d'instance => URL de base
     */
    public function getAllTargets(): array;

    public function getTarget(string $instance): ?string;
}
