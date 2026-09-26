<?php

declare(strict_types=1);

namespace App\Interface\GitHub;

interface ChangelogFetcherInterface
{
    /**
     * @return list<array{number: int, title: string, date: string, url: string, mergedAt: string}>
     */
    public function fetchEntries(): array;
}
