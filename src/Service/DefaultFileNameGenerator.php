<?php

namespace App\Service;

class DefaultFileNameGenerator implements FileNameGeneratorInterface
{
    public function generate(string $originalName): string
    {
        // On ne garde que le nom de base (pas de chemin)
        $basename = pathinfo($originalName, PATHINFO_BASENAME);
        // Translittération unicode → ASCII (ex: é → e)
        $basename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);
        // Remplace les espaces par des tirets
        $basename = str_replace(' ', '-', $basename);
        // Supprime tout caractère non alphanumérique, point, tiret ou underscore
        $basename = preg_replace('/[^A-Za-z0-9._-]/', '', $basename);
        // Protection contre les noms vides
        if (empty($basename)) {
            $basename = 'file';
        }
        return uniqid() . '_' . $basename;
    }
}
