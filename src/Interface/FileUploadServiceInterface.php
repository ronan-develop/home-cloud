<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Contrat de création de fichier depuis un upload web (session auth).
 * Symétrique à CreateFileServiceInterface (upload API Platform) — cf. #440.
 */
interface FileUploadServiceInterface
{
    /**
     * @throws BadRequestHttpException si l'extension est bloquée
     */
    public function createFromUpload(
        UploadedFile $uploadedFile,
        User $owner,
        ?string $folderId = null,
    ): File;
}
