<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface FileActionServiceInterface
{
    /**
     * @throws BadRequestHttpException si le nom est invalide ou déjà pris dans le dossier
     */
    public function rename(File $file, string $newName): void;

    /**
     * @throws BadRequestHttpException si déplacement créerait un cycle ou nom déjà pris
     */
    public function move(File $file, Folder $targetFolder, User $requester): void;

    public function delete(File $file): void;
}
