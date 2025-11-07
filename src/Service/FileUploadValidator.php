<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadValidator
{
    /**
     * Valide les règles métier sur le fichier uploadé
     * @throws \InvalidArgumentException en cas d'échec
     */
    public function validate(UploadedFile $file): void
    {
        // Interdire les fichiers exécutables (MIME et extension)
        $forbiddenMimeTypes = [
            'application/x-msdownload', // .exe
            'application/x-sh',         // .sh
            'application/x-php',        // .php
            'application/x-python',     // .py
            'application/x-perl',       // .pl
            'application/x-csh',        // .csh
            'application/x-shellscript', // .bash, .zsh, etc.
            'application/x-dosexec',    // .exe (autre)
            'application/x-bash',       // .bash
            'application/x-ruby',       // .rb
            'application/x-java-applet', // .jar
            'application/x-msdos-program', // .com
        ];
        if (in_array($file->getMimeType(), $forbiddenMimeTypes, true)) {
            throw new \InvalidArgumentException('Ce type de fichier est interdit (exécutable).');
        }

        // Interdire par extension
        $forbiddenExtensions = [
            'exe',
            'sh',
            'php',
            'py',
            'pl',
            'csh',
            'bash',
            'zsh',
            'rb',
            'jar',
            'com',
            'bat',
            'cmd',
            'msi',
            'vbs',
            'js',
            'scr',
            'dll',
            'bin'
        ];
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (in_array($extension, $forbiddenExtensions, true)) {
            throw new \InvalidArgumentException('Ce type de fichier est interdit (extension exécutable).');
        }
    }
}
