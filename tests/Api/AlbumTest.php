<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class AlbumTest extends AuthenticatedApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser();
    }

    private function createMedia(): Media
    {
        $user = new User('media-owner@example.com', 'Owner');
        $this->em->persist($user);
        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/test.jpg', $folder, $user);
        $this->em->persist($file);
        $media = new Media($file, 'photo');
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    // --- GET /api/v1/albums ---

    public function testGetCollectionReturnsEmptyArray(): void
    {
        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/albums', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([], $response->toArray());
    }

    public function testGetCollectionReturnsAlbums(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Vacances', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/albums', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertSame('Vacances', $data[0]['name']);
    }

    // --- GET /api/v1/albums/{id} ---

    public function testGetAlbumReturns200WithCorrectStructure(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Été 2026', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/albums/'.$album->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Été 2026', $data['name']);
        $this->assertArrayHasKey('ownerId', $data);
        $this->assertArrayHasKey('mediaCount', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertSame(0, $data['mediaCount']);
    }

    public function testGetAlbumReturns404WhenNotFound(): void
    {
        $this->createAuthenticatedClient()->request('GET', '/api/v1/albums/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- POST /api/v1/albums ---

    public function testPostAlbumCreates201(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/albums', [
            'json' => [
                'name' => 'Famille',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Famille', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('mediaCount', $data);
        $this->assertSame(0, $data['mediaCount']);
    }

    public function testPostAlbumReturns400WhenNameMissing(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/albums', [
            'json' => ['ownerId' => (string) $owner->getId()],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostAlbumReturns404WhenOwnerNotFound(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/v1/albums', [
            'json' => [
                'name' => 'Test',
                'ownerId' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // --- PATCH /api/v1/albums/{id} ---

    public function testPatchAlbumRenamesIt(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Ancien nom', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('PATCH', '/api/v1/albums/'.$album->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Nouveau nom'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('Nouveau nom', $response->toArray()['name']);
    }

    public function testPatchAlbumReturns404WhenNotFound(): void
    {
        $this->createAuthenticatedClient()->request('PATCH', '/api/v1/albums/00000000-0000-0000-0000-000000000000', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Test'],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // --- DELETE /api/v1/albums/{id} ---

    public function testDeleteAlbumReturns204(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('À supprimer', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/albums/'.$album->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteAlbumDoesNotDeleteMedias(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Avec médias', $owner);
        $media = $this->createMedia();
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/albums/'.$album->getId());

        $this->assertResponseStatusCodeSame(204);

        // Le media existe toujours en base
        $this->em->clear();
        $this->assertNotNull($this->em->find(Media::class, $media->getId()));
    }

    public function testDeleteAlbumReturns404WhenNotFound(): void
    {
        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/albums/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- POST /api/v1/albums/{id}/medias ---

    public function testAddMediaToAlbumReturns200(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Avec un média', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $media = $this->createMedia();

        $response = $this->createAuthenticatedClient()->request(
            'POST',
            '/api/v1/albums/'.$album->getId().'/medias',
            ['json' => ['mediaId' => (string) $media->getId()]]
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(1, $response->toArray()['mediaCount']);
    }

    public function testAddMediaToAlbumIdempotent(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Idempotent', $owner);
        $media = $this->createMedia();
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();

        // Ajouter le même média une 2e fois
        $response = $this->createAuthenticatedClient()->request(
            'POST',
            '/api/v1/albums/'.$album->getId().'/medias',
            ['json' => ['mediaId' => (string) $media->getId()]]
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(1, $response->toArray()['mediaCount']); // toujours 1
    }

    public function testAddMediaToAlbumReturns404WhenAlbumNotFound(): void
    {
        $media = $this->createMedia();

        $this->createAuthenticatedClient()->request(
            'POST',
            '/api/v1/albums/00000000-0000-0000-0000-000000000000/medias',
            ['json' => ['mediaId' => (string) $media->getId()]]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddMediaToAlbumReturns404WhenMediaNotFound(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new \App\Entity\Album('Test', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $this->createAuthenticatedClient()->request(
            'POST',
            '/api/v1/albums/'.$album->getId().'/medias',
            ['json' => ['mediaId' => '00000000-0000-0000-0000-000000000000']]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // --- DELETE /api/v1/albums/{id}/medias/{mediaId} ---

    public function testRemoveMediaFromAlbumReturns200(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $media = $this->createMedia();
        $album = new \App\Entity\Album('À retirer', $owner);
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request(
            'DELETE',
            '/api/v1/albums/'.$album->getId().'/medias/'.$media->getId()
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(0, $response->toArray()['mediaCount']);
    }

    public function testRemoveMediaFromAlbumReturns404WhenAlbumNotFound(): void
    {
        $media = $this->createMedia();

        $this->createAuthenticatedClient()->request(
            'DELETE',
            '/api/v1/albums/00000000-0000-0000-0000-000000000000/medias/'.$media->getId()
        );

        $this->assertResponseStatusCodeSame(404);
    }
}
