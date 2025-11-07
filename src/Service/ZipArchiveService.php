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
    public function createZipResponse(array $files, string $archiveName = 'mes-fichiers-homecloud.zip', ?string $userId = null): ?BinaryFileResponse
    {
        if (empty($files)) {
            return null;
        }
        $zipPath = sys_get_temp_dir() . '/homecloud_' . ($userId ?? 'nouser') . '_' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return null;
        }
        foreach ($files as $file) {
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
