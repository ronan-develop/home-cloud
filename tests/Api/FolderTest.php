<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Folder;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class FolderTest extends AuthenticatedApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    // --- GET /api/v1/folders/{id} ---
    public function testGetFolderReturns200WithCorrectStructure(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('Documents', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/folders/' . $folder->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Documents', $data['name']);
        $this->assertArrayHasKey('parentId', $data);
        $this->assertArrayHasKey('ownerId', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNull($data['parentId']);
    }

    public function testGetFolderReturns404WhenNotFound(): void
    {
        $this->createUser();
        $this->createAuthenticatedClient()->request('GET', '/api/v1/folders/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    // --- GET /api/v1/folders ---
    public function testGetCollectionReturnsFolders(): void
    {
        $owner = $this->createUser();
        $this->em->persist(new Folder('Photos', $owner));
        $this->em->persist(new Folder('Videos', $owner));
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/folders', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    // --- POST /api/v1/folders ---
    public function testPostFolderCreates201(): void
    {
        $owner = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'Music',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Music', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNull($data['parentId']);
    }

    public function testPostFolderWithParentCreates201(): void
    {
        $owner = $this->createUser();
        $parent = new Folder('Root', $owner);
        $this->em->persist($parent);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'SubFolder',
                'ownerId' => (string) $owner->getId(),
                'parentId' => (string) $parent->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame((string) $parent->getId(), $data['parentId']);
    }

    public function testPostFolderReturns400WhenNameIsMissing(): void
    {
        $owner = $this->createUser();
        $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => ['ownerId' => (string) $owner->getId()],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    // --- PATCH /api/v1/folders/{id} ---
    public function testPatchFolderUpdatesName(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('OldName', $owner);
        $this->em->persist($folder);
        $this->em->flush();
        // Capturer les IDs AVANT le clear
        $ownerIdBefore = (string) $owner->getId();
        $folderIdBefore = (string) $folder->getId();
        $folderOwnerIdBefore = (string) $folder->getOwner()->getId();
        $this->em->clear();
        // Recharger depuis la DB
        $folderReloaded = $this->em->getRepository(Folder::class)->find($folderIdBefore);
        $ownerReloaded = $this->em->getRepository(User::class)->find($ownerIdBefore);
        // 🔍 DEBUG COMPLET
        dump([
            '=== AVANT CLEAR ===' => [
                'owner_id' => $ownerIdBefore,
                'folder_owner_id' => $folderOwnerIdBefore,
                'owner_email' => $owner->getEmail(),
            ],
            '=== APRÈS RELOAD ===' => [
                'owner_reloaded_id' => (string) $ownerReloaded->getId(),
                'folder_reloaded_owner_id' => (string) $folderReloaded->getOwner()->getId(),
                'owner_reloaded_email' => $ownerReloaded->getEmail(),
                'folder_reloaded_owner_email' => $folderReloaded->getOwner()->getEmail(),
            ],
            '=== COMPARAISON ===' => [
                'ids_match' => $ownerReloaded->getId()->equals($folderReloaded->getOwner()->getId()),
                'same_object' => $ownerReloaded === $folderReloaded->getOwner(),
                'string_ids_match' => (string) $ownerReloaded->getId() === (string) $folderReloaded->getOwner()->getId(),
            ],
        ]);
        // Vérifier l'utilisateur authentifié dans le client
        $client = $this->createAuthenticatedClient($ownerReloaded);
        // 🔍 DEBUG: Vérifier l'utilisateur dans le TokenStorage
        $tokenStorage = static::getContainer()->get(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class);
        $token = $tokenStorage->getToken();
        dump([
            '=== TOKEN STORAGE ===' => [
                'has_token' => $token !== null,
                'user_in_token' => $token?->getUser(),
                'user_id_in_token' => $token?->getUser() instanceof \App\Entity\User ? (string) $token->getUser()->getId() : null,
                'user_email_in_token' => $token?->getUser() instanceof \App\Entity\User ? $token->getUser()->getEmail() : null,
            ],
        ]);
        $response = $client->request(
            'PATCH',
            '/api/v1/folders/' . $folderReloaded->getId(),
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
                'json' => ['name' => 'NewName'],
            ]
        );
        // Si ça échoue, afficher la réponse complète
        if ($response->getStatusCode() !== 200) {
            dump([
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->toArray(false),
            ]);
        }
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('NewName', $response->toArray()['name']);
    }

    // --- DELETE /api/v1/folders/{id} ---
    public function testDeleteFolderReturns204(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('ToDelete', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/folders/' . $folder->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteFolderReturns404WhenNotFound(): void
    {
        $this->createUser();
        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/folders/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPatchFolderReturns400WhenParentIsSelf(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Mon Dossier', $user);
        $this->em->persist($folder);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('PATCH', '/api/v1/folders/' . $folder->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['parentId' => (string) $folder->getId()],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // --- mediaType ---
    public function testFolderDefaultMediaTypeIsGeneral(): void
    {
        $owner = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name'    => 'My Folder',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('general', $response->toArray()['mediaType']);
    }

    public function testPostFolderWithMediaTypePhoto(): void
    {
        $owner = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name'      => 'Photos 2024',
                'ownerId'   => (string) $owner->getId(),
                'mediaType' => 'photo',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('photo', $response->toArray()['mediaType']);
    }

    public function testPostFolderWithInvalidMediaTypeReturns400(): void
    {
        $owner = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name'      => 'Invalid',
                'ownerId'   => (string) $owner->getId(),
                'mediaType' => 'invalid_type',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPatchFolderUpdatesMediaType(): void
    {
        $owner  = $this->createUser();
        $folder = new Folder('Folder', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('PATCH', '/api/v1/folders/' . $folder->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['mediaType' => 'video'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('video', $response->toArray()['mediaType']);
    }

    // --- DROITS (ownership) ---
    public function testPatchFolderByNonOwnerReturns403(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser('other@example.com');
        $folder = new Folder('Private', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($other);
        $client->request('PATCH', '/api/v1/folders/' . $folder->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Hacked'],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteFolderByNonOwnerReturns403(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser('other@example.com');
        $folder = new Folder('Private', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($other);
        $client->request('DELETE', '/api/v1/folders/' . $folder->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    // --- VALIDATION METIER ---
    public function testCannotCreateFolderWithDuplicateNameInSameParent(): void
    {
        $owner = $this->createUser();
        $parent = new Folder('Parent', $owner);
        $this->em->persist($parent);
        $this->em->persist(new Folder('Unique', $owner, $parent));
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'Unique',
                'ownerId' => (string) $owner->getId(),
                'parentId' => (string) $parent->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCannotPatchFolderWithDuplicateNameInSameParent(): void
    {
        $owner = $this->createUser();
        $parent = new Folder('Parent', $owner);
        $f1 = new Folder('A', $owner, $parent);
        $f2 = new Folder('B', $owner, $parent);
        $this->em->persist($parent);
        $this->em->persist($f1);
        $this->em->persist($f2);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('PATCH', '/api/v1/folders/' . $f2->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'A'],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCannotCreateFolderWithInvalidCharacters(): void
    {
        $owner = $this->createUser();
        $client = $this->createAuthenticatedClient($owner);
        $client->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'Invalid/Name',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCannotPatchFolderWithInvalidCharacters(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('Valid', $owner);
        $this->em->persist($folder);
        $this->em->flush();
        $client = $this->createAuthenticatedClient($owner);
        $client->request('PATCH', '/api/v1/folders/' . $folder->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Bad\\Name'],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }
}
