<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Media;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — fuite cross-tenant sur l'item ET la collection de médias (F2 de l'audit).
 *
 * MediaProvider ne filtrait jamais par propriétaire, ni sur GET /api/v1/medias/{id}
 * ni sur GET /api/v1/medias : n'importe quel utilisateur authentifié pouvait lire
 * les métadonnées de n'importe quel média.
 */
final class MediaCollectionOwnershipTest extends AuthenticatedApiTestCase
{
    private function makeMedia(string $owner, string $fileName): Media
    {
        $ownerEntity = $this->createUser($owner, 'password123', 'Owner');
        $folder = $this->createFolder('Docs_' . uniqid(), $ownerEntity, null, $this->em);

        $file = new File($fileName, 'image/jpeg', 10, 'x/' . uniqid() . '.jpg', $folder, $ownerEntity, false);
        $this->em->persist($file);

        $media = new Media($file, 'photo');
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    public function testCollectionOnlyReturnsOwnMedias(): void
    {
        $this->makeMedia('victim4@example.com', 'photo-victime.jpg');
        $this->makeMedia('attacker4@example.com', 'photo-attaquant.jpg');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker4@example.com');
        $browser->request('GET', '/api/v1/medias', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $data = json_decode((string) $browser->getResponse()->getContent(), true);

        $this->assertCount(1, $data, 'La collection ne doit contenir que le média de l\'attaquant.');
    }

    public function testItemCannotBeReadByOtherUser(): void
    {
        $media = $this->makeMedia('victim5@example.com', 'photo-victime.jpg');
        $mediaId = (string) $media->getId();
        $this->createUser('attacker5@example.com', 'password123', 'Attacker');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker5@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testOwnerCanReadOwnItem(): void
    {
        $media = $this->makeMedia('owner6@example.com', 'photo.jpg');
        $mediaId = (string) $media->getId();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('owner6@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }
}
