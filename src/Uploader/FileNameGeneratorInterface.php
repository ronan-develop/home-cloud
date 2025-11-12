<?php

namespace App\Uploader;

interface FileNameGeneratorInterface
{
    /**
     * Génère un nom de fichier à partir du nom original et de l'utilisateur (optionnel).
     *
     * @param string $originalName
     * @param int|string|null $userId
     * @return string
     */
    public function generate(string $originalName, $userId = null): string;
}
