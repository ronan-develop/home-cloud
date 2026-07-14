<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — vérification de non-régression pour la collection de dossiers (F2 de l'audit).
 *
 * Contrairement à FileProvider, FolderProvider filtrait déjà correctement par
 * propriétaire sur sa collection (repository->findFiltered($user, ...)). Ce test
 * documente et verrouille ce comportement correct.
 */
final class FolderCollectionOwnershipTest extends AuthenticatedApiTestCase
{
    public function testCollectionOnlyReturnsOwnFolders(): void
    {
        $victim = $this->createUser('victim3@example.com', 'password123', 'Victim');
        $this->createFolder('dossier-victime', $victim, null, $this->em);

        $attacker = $this->createUser('attacker3@example.com', 'password123', 'Attacker');
        $this->createFolder('dossier-attaquant', $attacker, null, $this->em);

        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker3@example.com');
        $browser->request('GET', '/api/v1/folders', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $body = $browser->getResponse()->getContent();

        $this->assertStringContainsString('dossier-attaquant', $body);
        $this->assertStringNotContainsString('dossier-victime', $body);
    }
}
