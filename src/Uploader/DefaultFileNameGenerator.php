<?php

namespace App\Uploader;

use App\Service\FileNameGeneratorInterface;

class DefaultFileNameGenerator implements FileNameGeneratorInterface
{
    public function generate(string $originalName): string
    {
        $basename = pathinfo($originalName, PATHINFO_BASENAME);
        $basename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);
        $basename = str_replace(' ', '-', $basename);
        $basename = preg_replace('/[^A-Za-z0-9._-]/', '', $basename);
        if (empty($basename)) {
            $basename = 'file';
        }
        return uniqid() . '_' . $basename;
    }
}
