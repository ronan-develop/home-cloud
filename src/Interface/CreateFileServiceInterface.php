<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface CreateFileServiceInterface
{
    /**
     * @throws BadRequestHttpException si validation fails
     * @throws NotFoundHttpException si user/folder not found
     */
    public function createFromUpload(
        UploadedFile $uploadedFile,
        string $ownerId,
        ?string $folderId = null,
        ?string $newFolderName = null,
    ): File;
}
