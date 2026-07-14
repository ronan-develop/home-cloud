<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Album;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — fuite cross-tenant sur l'item ET la collection d'albums (F2 de l'audit).
 *
 * AlbumProvider ne filtrait jamais par propriétaire, ni sur GET /api/v1/albums/{id}
 * ni sur GET /api/v1/albums.
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
}
