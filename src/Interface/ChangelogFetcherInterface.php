<?php

declare(strict_types=1);

namespace App\Interface;

interface ChangelogFetcherInterface
{
    /**
     * @return list<array{number: int, title: string, date: string, url: string}>
     */
    public function fetchEntries(): array;
}
