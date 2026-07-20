<?php

declare(strict_types=1);

namespace App\Interface;

interface PrTitleCleanerInterface
{
    public function clean(string $title): string;
}
