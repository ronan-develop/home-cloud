<?php

namespace App\Service;

class DefaultFileNameGenerator implements FileNameGeneratorInterface
{
    public function generate(string $originalName): string
    {
        return uniqid() . '_' . $originalName;
    }
}
