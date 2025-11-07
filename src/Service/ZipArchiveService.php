<?php

namespace App\Service;

use ZipArchive;
use App\Entity\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ZipArchiveService
{
    /**
     * Crée une archive ZIP à partir d'une liste de fichiers
     * @param File[] $files
     * @param string $archiveName
     * @param string|null $userId
     * @return BinaryFileResponse|null
     */
    public function createZipResponse(array $files, string $archiveName = 'mes-fichiers-homecloud.zip', ?string $userId = null): BinaryFileResponse
    {
        if (empty($files)) {
            throw new \RuntimeException('Aucun fichier à archiver.');
        }
        $zipPath = sys_get_temp_dir() . '/homecloud_' . ($userId ?? 'nouser') . '_' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive ZIP.');
        }
        foreach ($files as $file) {
            if (!file_exists($file->getPath())) {
                throw new \RuntimeException('Fichier manquant pour l\'archive ZIP : ' . $file->getName());
            }
            $zip->addFile($file->getPath(), $file->getName());
        }
        $zip->close();
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $archiveName
        );
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend(true);
        return $response;
    }
}
