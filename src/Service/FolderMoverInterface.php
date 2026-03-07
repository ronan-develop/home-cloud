<?php

namespace App\Service;

use App\Entity\Folder;
use App\Entity\User;

interface FolderMoverInterface
{
    public function moveContentsToUploads(Folder $folder, User $owner): Folder;
}
