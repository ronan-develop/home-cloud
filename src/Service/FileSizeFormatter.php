<?php

declare(strict_types=1);

namespace App\Service;

final class FileSizeFormatter
{
    private const array UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

    public function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' o';
        }

        $power = min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1);
        $value = $bytes / (1024 ** $power);
        $rounded = round($value, 2);

        $formatted = rtrim(rtrim(number_format($rounded, 2, ',', ''), '0'), ',');

        return $formatted . ' ' . self::UNITS[$power];
    }
}
