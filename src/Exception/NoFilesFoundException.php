<?php

namespace App\Exception;

class NoFilesFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Aucun fichier à télécharger.')
    {
        parent::__construct($message);
    }
}
