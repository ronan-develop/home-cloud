<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — IDOR sur le téléchargement de fichier (F3 de l'audit).
 *
 * FileDownloadController chargeait le fichier par ID et le streamait sans
 * aucun contrôle d'ownership : un utilisateur authentifié pouvait télécharger
 * le fichier de n'importe quel autre utilisateur.
 */
final class FileDownloadOwnershipTest extends AuthenticatedApiTestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
    }

    private function makeStoredFile(string $owner): File
    {
        $ownerEntity = $this->createUser($owner, 'password123', 'Owner');
        $folder = $this->createFolder('Docs_' . uniqid(), $ownerEntity, null, $this->em);

        $rel = 'audit/dl-' . uniqid() . '.txt';
        @mkdir($this->storageDir . '/audit', 0777, true);
        file_put_contents($this->storageDir . '/' . $rel, 'contenu privé du propriétaire');

        $file = new File('confidentiel.txt', 'text/plain', 30, $rel, $folder, $ownerEntity, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testAnonymousCannotDownloadFile(): void
    {
        $file = $this->makeStoredFile('victim1@example.com');
        $fileId = (string) $file->getId();
        $this->em->clear();

        static::createClient()->request('GET', '/api/v1/files/' . $fileId . '/download');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testOtherAuthenticatedUserCannotDownloadFile(): void
    {
        $file = $this->makeStoredFile('victim2@example.com');
        $fileId = (string) $file->getId();
        $this->createUser('attacker2@example.com', 'password123', 'Attacker');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker2@example.com');
        $browser->request('GET', '/api/v1/files/' . $fileId . '/download');

        $this->assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testOwnerCanDownloadOwnFile(): void
    {
        $file = $this->makeStoredFile('owner3@example.com');
        $fileId = (string) $file->getId();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('owner3@example.com');
        $browser->request('GET', '/api/v1/files/' . $fileId . '/download');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }
}
