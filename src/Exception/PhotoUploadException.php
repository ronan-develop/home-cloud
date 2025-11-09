<?php

namespace App\Exception;

class PhotoUploadException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $filename = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public static function forInvalidMime(string $filename): self
    {
        return new self("Type MIME non autorisé", 0, null, 'invalid_mime', $filename);
    }

    public static function forDirectory(string $message, ?string $directory = null): self
    {
        return new self($message, 0, null, 'dir_error', $directory);
    }

    public static function forMove(string $filename): self
    {
        return new self("Erreur lors du déplacement du fichier", 0, null, 'move_failed', $filename);
    }
}
