<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Media;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — IDOR sur les thumbnails de médias (F4 de l'audit).
 *
 * MediaThumbnailController chargeait le média par ID et le streamait sans
 * aucun contrôle d'ownership : un utilisateur authentifié pouvait afficher
 * la vignette d'un média appartenant à n'importe quel autre utilisateur.
 */
final class MediaThumbnailOwnershipTest extends AuthenticatedApiTestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
    }

    private function makeStoredMedia(string $owner): Media
    {
        $ownerEntity = $this->createUser($owner, 'password123', 'Owner');
        $folder = $this->createFolder('Docs_' . uniqid(), $ownerEntity, null, $this->em);

        $rel = 'audit/thumb-' . uniqid() . '.jpg';
        @mkdir($this->storageDir . '/audit', 0777, true);
        file_put_contents($this->storageDir . '/' . $rel, 'fake jpeg content');

        $file = new File('photo.jpg', 'image/jpeg', 20, 'audit/photo-' . uniqid() . '.jpg', $folder, $ownerEntity, false);
        $this->em->persist($file);

        $media = new Media($file, 'photo');
        $media->setThumbnailPath($rel);
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    public function testAnonymousCannotViewThumbnail(): void
    {
        $media = $this->makeStoredMedia('victim1@example.com');
        $mediaId = (string) $media->getId();
        $this->em->clear();

        static::createClient()->request('GET', '/api/v1/medias/' . $mediaId . '/thumbnail');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testOtherAuthenticatedUserCannotViewThumbnail(): void
    {
        $media = $this->makeStoredMedia('victim2@example.com');
        $mediaId = (string) $media->getId();
        $this->createUser('attacker2@example.com', 'password123', 'Attacker');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('attacker2@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId . '/thumbnail');

        $this->assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testOwnerCanViewOwnThumbnail(): void
    {
        $media = $this->makeStoredMedia('owner3@example.com');
        $mediaId = (string) $media->getId();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('owner3@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId . '/thumbnail');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }
}
