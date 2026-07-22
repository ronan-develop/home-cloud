<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;

/**
 * TDD RED — faille IDOR découverte pendant #339 : AlbumAddMediaController et
 * AlbumRemoveMediaController n'appliquaient aucun contrôle d'ownership.
 * N'importe quel utilisateur authentifié pouvait associer/retirer n'importe
 * quel Media (même appartenant à un autre utilisateur) sur n'importe quel
 * Album (même appartenant à un autre utilisateur), en connaissant juste les
 * UUID.
 *
 * Le contrôle attendu, cohérent avec l'existant :
 * - Album : AlbumVoter::VIEW (owner OU partage actif, cf. AlbumWebController
 *   qui applique déjà ce même voter sur les routes web équivalentes)
 * - Media : Media::isOwnedBy() (déjà utilisé ailleurs, encapsule
 *   getFile()->getOwner())
 */
final class AlbumMediaOwnershipTest extends AuthenticatedApiTestCase
{
    private function createAlbumFor(User $user, string $name = 'Album'): Album
    {
        $album = new Album($name, $user);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    private function createMediaFor(User $user, string $name = 'photo.jpg'): Media
    {
        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);

        $file = new File($name, 'image/jpeg', 1024, "test/{$name}", $folder, $user);
        $this->em->persist($file);

        $media = new Media($file, 'photo');
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    public function testCannotAddAnotherUsersMediaToOwnAlbum(): void
    {
        $alice = $this->createUser('alice-idor@example.com', 'password123', 'Alice');
        $bob   = $this->createUser('bob-idor@example.com', 'password123', 'Bob');

        $aliceAlbum = $this->createAlbumFor($alice);
        $bobsMedia  = $this->createMediaFor($bob, 'bob-private.jpg');

        $client = $this->createAuthenticatedKernelBrowser($alice);
        $client->request(
            'POST',
            '/api/v1/albums/' . $aliceAlbum->getId() . '/medias',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mediaId' => (string) $bobsMedia->getId()]),
        );

        $this->assertResponseStatusCodeSame(403);

        $this->em->clear();
        $refreshedAlbum = $this->em->getRepository(Album::class)->find($aliceAlbum->getId());
        $this->assertCount(0, $refreshedAlbum->getMedias(), 'Le média de Bob ne doit pas avoir été associé');
    }

    public function testCannotAddMediaToAnotherUsersAlbum(): void
    {
        $alice = $this->createUser('alice-idor2@example.com', 'password123', 'Alice');
        $bob   = $this->createUser('bob-idor2@example.com', 'password123', 'Bob');

        $bobsAlbum  = $this->createAlbumFor($bob);
        $alicesMedia = $this->createMediaFor($alice, 'alice-photo.jpg');

        $client = $this->createAuthenticatedKernelBrowser($alice);
        $client->request(
            'POST',
            '/api/v1/albums/' . $bobsAlbum->getId() . '/medias',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mediaId' => (string) $alicesMedia->getId()]),
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCanAddOwnMediaToOwnAlbum(): void
    {
        $alice = $this->createUser('alice-idor3@example.com', 'password123', 'Alice');

        $album = $this->createAlbumFor($alice);
        $media = $this->createMediaFor($alice, 'my-photo.jpg');

        $client = $this->createAuthenticatedKernelBrowser($alice);
        $client->request(
            'POST',
            '/api/v1/albums/' . $album->getId() . '/medias',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['mediaId' => (string) $media->getId()]),
        );

        $this->assertResponseStatusCodeSame(200);
    }

    public function testCannotRemoveAnotherUsersMediaAssociation(): void
    {
        $alice = $this->createUser('alice-idor4@example.com', 'password123', 'Alice');
        $bob   = $this->createUser('bob-idor4@example.com', 'password123', 'Bob');

        $bobsAlbum = $this->createAlbumFor($bob);
        $bobsMedia = $this->createMediaFor($bob, 'bob-photo.jpg');
        $bobsAlbum->addMedia($bobsMedia);
        $this->em->flush();

        $client = $this->createAuthenticatedKernelBrowser($alice);
        $client->request(
            'DELETE',
            '/api/v1/albums/' . $bobsAlbum->getId() . '/medias/' . $bobsMedia->getId(),
        );

        $this->assertResponseStatusCodeSame(403);

        $this->em->clear();
        $refreshedAlbum = $this->em->getRepository(Album::class)->find($bobsAlbum->getId());
        $this->assertCount(1, $refreshedAlbum->getMedias(), 'Le média de Bob doit rester associé à son album');
    }

    public function testCanRemoveOwnMediaFromOwnAlbum(): void
    {
        $alice = $this->createUser('alice-idor5@example.com', 'password123', 'Alice');

        $album = $this->createAlbumFor($alice);
        $media = $this->createMediaFor($alice, 'my-photo.jpg');
        $album->addMedia($media);
        $this->em->flush();

        $client = $this->createAuthenticatedKernelBrowser($alice);
        $client->request(
            'DELETE',
            '/api/v1/albums/' . $album->getId() . '/medias/' . $media->getId(),
        );

        $this->assertResponseStatusCodeSame(200);
    }
}
