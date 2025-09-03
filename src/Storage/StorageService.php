<?php

namespace App\Storage;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StorageService
{
    public function __construct(private string $storagePath, private LoggerInterface $logger) {}

    /**
     * Store uploaded file under tenants/{tenantName}/ and return relative path
     */
    public function storeUploadedFile(UploadedFile $file, string $tenantName): string
    {
        $tenantDir = rtrim($this->storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantName;
        if (!is_dir($tenantDir)) {
            mkdir($tenantDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
        $target = $tenantDir . DIRECTORY_SEPARATOR . $filename;

        $file->move($tenantDir, $filename);

        $this->logger->info('Stored file for tenant', ['tenant' => $tenantName, 'path' => $target]);

        // return path relative to storage root
        return 'tenants/' . $tenantName . '/' . $filename;
    }
}
