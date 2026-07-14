<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\Share;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Sécurité — IDOR sur les thumbnails de médias (F4 de l'audit).
 *
 * MediaThumbnailController chargeait le média par ID et le streamait sans
 * aucun contrôle d'ownership : un utilisateur authentifié pouvait afficher
 * la vignette d'un média appartenant à n'importe quel autre utilisateur.
 *
 * Un partage actif sur le File sous-jacent (Share::RESOURCE_FILE) donne
 * accès à la vignette de son Media — pas de RESOURCE_MEDIA distinct, la
 * vignette est une simple représentation dérivée du fichier partagé.
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

    public function testGuestWithActiveShareOnFileCanViewThumbnail(): void
    {
        $media = $this->makeStoredMedia('owner4@example.com');
        $mediaId = (string) $media->getId();
        $owner = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'owner4@example.com']);
        $guest = $this->createUser('guest4@example.com', 'password123', 'Guest');

        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $media->getFile()->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('guest4@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId . '/thumbnail');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testGuestWithoutShareCannotViewThumbnail(): void
    {
        $media = $this->makeStoredMedia('owner5@example.com');
        $mediaId = (string) $media->getId();
        $this->createUser('guest5@example.com', 'password123', 'Guest');
        $this->em->clear();

        $browser = $this->createAuthenticatedKernelBrowser('guest5@example.com');
        $browser->request('GET', '/api/v1/medias/' . $mediaId . '/thumbnail');

        $this->assertSame(403, $browser->getResponse()->getStatusCode());
    }
}
