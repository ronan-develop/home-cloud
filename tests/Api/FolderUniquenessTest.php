<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;

/**
 * Validation d'unicité du nom de dossier lors d'un déplacement (PATCH parentId).
 *
 * Cas non couverts par FolderCrudTest :
 * - Move seul (nom inchangé) → collision dans le nouveau parent
 * - Rename + Move simultanés → collision dans le nouveau parent
 */
final class FolderUniquenessTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    /** PATCH move uniquement — nom identique déjà présent dans le nouveau parent → 400 */
    public function testMoveFolderCausesNameCollisionReturns400(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $target = $this->createFolder('Target', $alice);
        $this->createFolder('SameName', $alice, $target);
        $toMove = $this->createFolder('SameName', $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/folders/' . $toMove->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['parentId' => (string) $target->getId()],
        ]);

        static::assertResponseStatusCodeSame(400);
    }

    /** PATCH rename + move — nouveau nom déjà présent dans le nouveau parent → 400 */
    public function testRenameAndMoveFolderCollisionInNewParentReturns400(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $target = $this->createFolder('Target', $alice);
        $this->createFolder('Existing', $alice, $target);
        $toMove = $this->createFolder('Original', $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/folders/' . $toMove->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => [
                'name'     => 'Existing',
                'parentId' => (string) $target->getId(),
            ],
        ]);

        static::assertResponseStatusCodeSame(400);
    }

    /** PATCH move — pas de collision dans le nouveau parent → 200 */
    public function testMoveFolderNoCollisionSucceeds(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $target = $this->createFolder('Target', $alice);
        $toMove = $this->createFolder('UniqueInTarget', $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/folders/' . $toMove->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['parentId' => (string) $target->getId()],
        ]);

        static::assertResponseStatusCodeSame(200);
        $this->assertSame((string) $target->getId(), $response->toArray()['parentId']);
    }
}
