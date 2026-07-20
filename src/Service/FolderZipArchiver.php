<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Folder;
use App\Interface\FolderZipArchiverInterface;
use App\Interface\StorageServiceInterface;

/**
 * Construit une archive zip d'un dossier et de tout son contenu (récursif).
 *
 * Parcourt `Folder::getChildren()` plutôt que `FolderRepository::findDescendantIds()` :
 * la structure de dossiers imbriqués nécessaire au zip (chemin relatif) est déjà
 * portée par la relation Doctrine bidirectionnelle, pas besoin de la CTE SQL.
 */
final readonly class FolderZipArchiver implements FolderZipArchiverInterface
{
    public function __construct(
        private StorageServiceInterface $storageService,
    ) {}

    public function archive(Folder $folder): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_folder_');
        unlink($tmpFile);
        $zipPath = $tmpFile . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive zip.');
        }

        $this->addFolderContents($zip, $folder, $folder->getName());

        $zip->close();

        return $zipPath;
    }

    private function addFolderContents(\ZipArchive $zip, Folder $folder, string $zipDir): void
    {
        $zip->addEmptyDir($zipDir);

        foreach ($folder->getFiles() as $file) {
            $absolutePath = $this->storageService->getAbsolutePath($file->getPath());
            if (file_exists($absolutePath)) {
                $zip->addFile($absolutePath, $zipDir . '/' . $file->getOriginalName());
            }
        }

        foreach ($folder->getChildren() as $child) {
            $this->addFolderContents($zip, $child, $zipDir . '/' . $child->getName());
        }
    }
}
