<?php

namespace App\File;

use App\Entity\File;
use App\Entity\User;
use App\Security\FileAccessManager;
use App\Security\FilePathSecurity;
use Doctrine\ORM\EntityManagerInterface;

class BulkFileDeleteService
{
    private EntityManagerInterface $em;
    private FileAccessManager $fileAccessManager;
    private FilePathSecurity $filePathSecurity;

    public function __construct(EntityManagerInterface $em, FileAccessManager $fileAccessManager, FilePathSecurity $filePathSecurity)
    {
        $this->em = $em;
        $this->fileAccessManager = $fileAccessManager;
        $this->filePathSecurity = $filePathSecurity;
    }

    /**
     * Supprime en masse les fichiers pour un utilisateur
     * @param array<int|string> $ids Liste des IDs de fichiers
     * @param User $user Utilisateur courant
     * @return int Nombre de fichiers supprimés
     */
    public function deleteFiles(array $ids, User $user): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $file = $this->em->getRepository(File::class)->find($id);
            if (!$file) {
                continue;
            }
            try {
                $this->fileAccessManager->assertDeleteAccess($file, $user);
                $realPath = $this->filePathSecurity->assertSafePath($file->getPath());
                $this->filePathSecurity->deleteFile($realPath);
                $this->em->remove($file);
                $count++;
            } catch (\Throwable $e) {
                continue;
            }
        }
        $this->em->flush();
        return $count;
    }
}
