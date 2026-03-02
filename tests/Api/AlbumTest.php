<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;

final class AlbumTest extends ApiTestCase
{
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
        $this->createAuthenticatedUser();
    }

    private function createAuthenticatedUser(): User
    {
        $user = new User('alice@example.com', 'Alice');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function createUser(string $email, string $name = 'User'): User
    {
        $user = new User($email, $name);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
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

    public function testGetCollectionReturnsEmptyArray(): void
    {
        $response = static::createClient()->request('GET', '/api/v1/albums', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame([], $response->toArray());
    }

    public function testGetCollectionReturnsAlbums(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('Vacances', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/albums', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        Assert::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();
        Assert::assertCount(1, $data);
        Assert::assertSame('Vacances', $data[0]['name']);
    }

    public function testGetAlbumReturns200WithCorrectStructure(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('Été 2026', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/albums/' . $album->getId());

        Assert::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();
        Assert::assertArrayHasKey('id', $data);
        Assert::assertSame('Été 2026', $data['name']);
        Assert::assertArrayHasKey('ownerId', $data);
        Assert::assertArrayHasKey('mediaCount', $data);
        Assert::assertArrayHasKey('createdAt', $data);
        Assert::assertSame(0, $data['mediaCount']);
    }

    public function testGetAlbumReturns404WhenNotFound(): void
    {
        $response = static::createClient()->request('GET', '/api/v1/albums/00000000-0000-0000-0000-000000000000');
        Assert::assertSame(404, $response->getStatusCode());
    }

    public function testPostAlbumCreates201(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);

        $response = static::createClient()->request('POST', '/api/v1/albums', [
            'json' => [
                'name' => 'Famille',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);
        Assert::assertSame(201, $response->getStatusCode());
        $data = $response->toArray();
        Assert::assertSame('Famille', $data['name']);
        Assert::assertArrayHasKey('id', $data);
        Assert::assertArrayHasKey('mediaCount', $data);
        Assert::assertSame(0, $data['mediaCount']);
    }

    public function testPostAlbumReturns400WhenNameMissing(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $response = static::createClient()->request('POST', '/api/v1/albums', [
            'json' => ['ownerId' => (string) $owner->getId()],
        ]);

        Assert::assertSame(400, $response->getStatusCode());
    }

    public function testPostAlbumReturns404WhenOwnerNotFound(): void
    {
        $response = static::createClient()->request('POST', '/api/v1/albums', [
            'json' => [
                'name' => 'Test',
                'ownerId' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        Assert::assertSame(404, $response->getStatusCode());
    }

    public function testPatchAlbumRenamesIt(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('Ancien nom', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('PATCH', '/api/v1/albums/' . $album->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Nouveau nom'],
        ]);

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame('Nouveau nom', $response->toArray()['name']);
    }

    public function testPatchAlbumReturns400IfNameHasInvalidChars(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('NomValide', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('PATCH', '/api/v1/albums/' . $album->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Nom/Interdit'],
        ]);
        Assert::assertSame(400, $response->getStatusCode());
    }

    public function testPatchAlbumReturns403IfNotOwner(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $other = $this->createUser('bob@example.com');
        $album = new Album('Privé', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('PATCH', '/api/v1/albums/' . $album->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Hack'],
        ]);
        Assert::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteAlbumReturns403IfNotOwner(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $other = $this->createUser('bob@example.com');
        $album = new Album('À protéger', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('DELETE', '/api/v1/albums/' . $album->getId());
        Assert::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteAlbumReturns204WhenOwner(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('À supprimer', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request('DELETE', '/api/v1/albums/' . $album->getId());
        Assert::assertSame(204, $response->getStatusCode());
    }

    public function testAddMediaToAlbumIdempotent(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('Idempotent', $owner);
        $media = $this->createMedia();
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request(
            'POST',
            '/api/v1/albums/' . $album->getId() . '/medias',
            ['json' => ['mediaId' => (string) $media->getId()]]
        );

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(1, $response->toArray()['mediaCount']);
    }

    public function testAddMediaToAlbumReturns404WhenAlbumNotFound(): void
    {
        $media = $this->createMedia();

        $response = static::createClient()->request(
            'POST',
            '/api/v1/albums/00000000-0000-0000-0000-000000000000/medias',
            ['json' => ['mediaId' => (string) $media->getId()]]
        );

        Assert::assertSame(404, $response->getStatusCode());
    }

    public function testAddMediaToAlbumReturns404WhenMediaNotFound(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $album = new Album('Test', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request(
            'POST',
            '/api/v1/albums/' . $album->getId() . '/medias',
            ['json' => ['mediaId' => '00000000-0000-0000-0000-000000000000']]
        );

        Assert::assertSame(404, $response->getStatusCode());
    }

    public function testRemoveMediaFromAlbumReturns200(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $media = $this->createMedia();
        $album = new Album('À retirer', $owner);
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();

        $response = static::createClient()->request(
            'DELETE',
            '/api/v1/albums/' . $album->getId() . '/medias/' . $media->getId()
        );

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(0, $response->toArray()['mediaCount']);
    }

    public function testRemoveMediaFromAlbumReturns404WhenAlbumNotFound(): void
    {
        $media = $this->createMedia();

        $response = static::createClient()->request(
            'DELETE',
            '/api/v1/albums/00000000-0000-0000-0000-000000000000/medias/' . $media->getId()
        );

        Assert::assertSame(404, $response->getStatusCode());
    }
}
