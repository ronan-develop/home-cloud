<?php

namespace App\Security;

use App\Entity\File;

class FileMimeTypeGuesser
{
    public function getSafeMimeType(File $file): string
    {
        $mime = $file->getMimeType();
        if (!preg_match('/^[a-z0-9\-\.]+\/[a-z0-9\-\.]+$/i', $mime)) {
            return 'application/octet-stream';
        }
        return $mime;
    }
}
