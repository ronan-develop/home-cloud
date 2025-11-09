<?php

namespace App\Service;

interface FileNameGeneratorInterface
{
    /**
     * Génère un nom de fichier unique à partir du nom original
     * @param string $originalName
     * @return string
     */
    public function generate(string $originalName): string;
}
