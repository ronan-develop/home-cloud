<?php

declare(strict_types=1);

namespace App\Interface;

interface ActivityTrackerInterface
{
    public function recordActivity(): void;

    public function getLastActivityAt(): ?\DateTimeImmutable;
}
