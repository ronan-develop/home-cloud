<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — fuite cross-tenant sur la collection de fichiers (F2 de l'audit).
 *
 * FileRepository::findFiltered()/countFiltered() ne filtraient jamais par
 * propriétaire : un utilisateur authentifié voyait les fichiers de TOUS les
 * autres utilisateurs sur GET /api/v1/files.
 */
final class FileCollectionOwnershipTest extends AuthenticatedApiTestCase
{
    private function makeFile(string $owner, string $name): File
    {
        $ownerEntity = $this->createUser($owner, 'password123', 'Owner');
        $folder = $this->createFolder('Docs_' . uniqid(), $ownerEntity, null, $this->em);

        $file = new File($name, 'text/plain', 10, 'x/' . uniqid() . '.txt', $folder, $ownerEntity, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testCollectionOnlyReturnsOwnFiles(): void
    {
        $this->makeFile('victim@example.com', 'secret-victime.txt');
        $this->makeFile('attacker@example.com', 'fichier-attaquant.txt');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker@example.com');
        $browser->request('GET', '/api/v1/files', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $body = $browser->getResponse()->getContent();

        $this->assertStringContainsString('fichier-attaquant.txt', $body);
        $this->assertStringNotContainsString('secret-victime.txt', $body);
    }

    public function testCollectionCountOnlyCountsOwnFiles(): void
    {
        $this->makeFile('victim2@example.com', 'a.txt');
        $this->makeFile('victim2@example.com', 'b.txt');
        $this->makeFile('attacker2@example.com', 'c.txt');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker2@example.com');
        $browser->request('GET', '/api/v1/files', server: ['HTTP_ACCEPT' => 'application/json']);

        $data = json_decode((string) $browser->getResponse()->getContent(), true);

        $this->assertCount(1, $data, 'La collection ne doit contenir que le fichier de l\'attaquant.');
    }
}
