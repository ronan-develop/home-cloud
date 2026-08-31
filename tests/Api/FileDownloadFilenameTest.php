<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * #335 — le fichier téléchargé doit porter le nom réel affiché dans
 * l'interface (File::$originalName), pas un nom technique de stockage.
 *
 * Couvre aussi le cas des caractères spéciaux/accents (RFC 6266) : le nom
 * doit survivre intact au header Content-Disposition, via le paramètre
 * filename* (UTF-8 encodé) que Symfony ajoute automatiquement.
 */
final class FileDownloadFilenameTest extends AuthenticatedApiTestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
    }

    private function makeStoredFile(string $originalName): File
    {
        $owner = $this->createUser('dl-filename@example.com', 'password123', 'Owner');
        $folder = $this->createFolder('Docs_' . uniqid(), $owner, null, $this->em);

        $rel = 'dl-filename/' . uniqid() . '.bin';
        @mkdir($this->storageDir . '/dl-filename', 0777, true);
        file_put_contents($this->storageDir . '/' . $rel, 'contenu');

        $file = new File($originalName, 'text/plain', 7, $rel, $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testDownloadRestituesOriginalNameWithAccentsAndSpaces(): void
    {
        $originalName = 'Facture été 2026 – été.pdf';
        $file = $this->makeStoredFile($originalName);
        $fileId = (string) $file->getId();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('dl-filename@example.com');
        $browser->request('GET', '/api/v1/files/' . $fileId . '/download');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $disposition = (string) $browser->getResponse()->headers->get('Content-Disposition');

        $this->assertStringStartsWith('attachment', $disposition);
        // RFC 6266 : le nom UTF-8 doit être porté par le paramètre filename*,
        // percent-encodé (RFC 5987) — pas transformé/tronqué.
        $this->assertStringContainsString(
            "filename*=utf-8''" . rawurlencode($originalName),
            $disposition,
        );
    }

    public function testDownloadDoesNotUseStorageUuidAsFilename(): void
    {
        $file = $this->makeStoredFile('rapport-mensuel.docx');
        $fileId = (string) $file->getId();
        $storedBasename = basename($file->getPath());
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('dl-filename@example.com');
        $browser->request('GET', '/api/v1/files/' . $fileId . '/download');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $disposition = (string) $browser->getResponse()->headers->get('Content-Disposition');

        $this->assertStringContainsString('rapport-mensuel.docx', $disposition);
        $this->assertStringNotContainsString($storedBasename, $disposition);
    }
}
