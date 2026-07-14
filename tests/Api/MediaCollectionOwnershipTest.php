<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\Share;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — fuite cross-tenant sur l'item ET la collection de médias (F2 de l'audit).
 *
 * MediaProvider ne filtrait jamais par propriétaire, ni sur GET /api/v1/medias/{id}
 * ni sur GET /api/v1/medias : n'importe quel utilisateur authentifié pouvait lire
 * les métadonnées de n'importe quel média.
 *
 * La collection reste ownership-only (un invité ne doit pas lister TOUS les
 * médias qui lui sont partagés en appelant /medias sans filtre), mais l'item
 * doit accepter un partage actif sur le File sous-jacent, sur le même modèle
 * que FileProvider.
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

    public function testGuestWithActiveShareOnFileCanReadItem(): void
    {
        $media = $this->makeMedia('owner7@example.com', 'photo.jpg');
        $mediaId = (string) $media->getId();
        $owner = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'owner7@example.com']);
        $guest = $this->createUser('guest7@example.com', 'password123', 'Guest');

        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $media->getFile()->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('guest7@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId, server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }
}
