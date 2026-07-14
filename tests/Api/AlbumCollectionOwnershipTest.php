<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Album;
use App\Entity\Share;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — fuite cross-tenant sur l'item ET la collection d'albums (F2 de l'audit).
 *
 * AlbumProvider ne filtrait jamais par propriétaire, ni sur GET /api/v1/albums/{id}
 * ni sur GET /api/v1/albums.
 *
 * La collection reste ownership-only (même logique que Media/File : un invité
 * ne doit pas lister tous les albums qui lui sont partagés), mais l'item doit
 * accepter un partage actif (Share::RESOURCE_ALBUM).
 */
final class AlbumCollectionOwnershipTest extends AuthenticatedApiTestCase
{
    private function makeAlbum(string $owner, string $name): Album
    {
        $ownerEntity = $this->createUser($owner, 'password123', 'Owner');
        $album = new Album($name, $ownerEntity);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    public function testCollectionOnlyReturnsOwnAlbums(): void
    {
        $this->makeAlbum('victim7@example.com', 'album-victime');
        $this->makeAlbum('attacker7@example.com', 'album-attaquant');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker7@example.com');
        $browser->request('GET', '/api/v1/albums', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $body = $browser->getResponse()->getContent();

        $this->assertStringContainsString('album-attaquant', $body);
        $this->assertStringNotContainsString('album-victime', $body);
    }

    public function testItemCannotBeReadByOtherUser(): void
    {
        $album = $this->makeAlbum('victim8@example.com', 'album-victime');
        $albumId = (string) $album->getId();
        $this->createUser('attacker8@example.com', 'password123', 'Attacker');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker8@example.com');
        $browser->request('GET', '/api/v1/albums/' . $albumId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testOwnerCanReadOwnItem(): void
    {
        $album = $this->makeAlbum('owner9@example.com', 'mon-album');
        $albumId = (string) $album->getId();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('owner9@example.com');
        $browser->request('GET', '/api/v1/albums/' . $albumId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testGuestWithActiveShareCanReadItem(): void
    {
        $album = $this->makeAlbum('owner10@example.com', 'album-partage');
        $albumId = (string) $album->getId();
        $owner = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'owner10@example.com']);
        $guest = $this->createUser('guest10@example.com', 'password123', 'Guest');

        $share = new Share($owner, $guest, Share::RESOURCE_ALBUM, $album->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('guest10@example.com');
        $browser->request('GET', '/api/v1/albums/' . $albumId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }
}
