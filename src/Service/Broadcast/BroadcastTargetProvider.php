<?php

declare(strict_types=1);

namespace App\Service\Broadcast;

use App\Interface\Broadcast\BroadcastTargetProviderInterface;

final readonly class BroadcastTargetProvider implements BroadcastTargetProviderInterface
{
    public function __construct(
        private string $configPath,
    ) {}

    public function getAllTargets(): array
    {
        return require $this->configPath;
    }

    public function getTarget(string $instance): ?string
    {
        return $this->getAllTargets()[$instance] ?? null;
    }
}
