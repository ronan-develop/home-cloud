<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Folder;
use App\Entity\User;

interface FolderMoverInterface
{
    public function moveContentsToUploads(Folder $folder, User $owner): Folder;
}
