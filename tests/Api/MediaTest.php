<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaTest extends ApiTestCase
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

    private function createMedia(string $type = 'photo'): Media
    {
        $user = new User('media@example.com', 'Owner');
        $this->em->persist($user);
        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/test.jpg', $folder, $user);
        $this->em->persist($file);
        $media = new Media($file, $type);
        $media->setWidth(1920);
        $media->setHeight(1080);
        $media->setCameraModel('Apple iPhone 15');
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    // --- GET /api/v1/medias/{id} ---

    public function testGetMediaReturns200WithCorrectStructure(): void
    {
        $media = $this->createMedia();

        $response = static::createClient()->request('GET', '/api/v1/medias/'.$media->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('mediaType', $data);
        $this->assertArrayHasKey('fileId', $data);
        $this->assertArrayHasKey('width', $data);
        $this->assertArrayHasKey('height', $data);
        $this->assertArrayHasKey('cameraModel', $data);
        $this->assertArrayHasKey('takenAt', $data);
        $this->assertArrayHasKey('gpsLat', $data);
        $this->assertArrayHasKey('gpsLon', $data);
        $this->assertArrayHasKey('thumbnailPath', $data);
        $this->assertSame('photo', $data['mediaType']);
        $this->assertSame(1920, $data['width']);
        $this->assertSame('Apple iPhone 15', $data['cameraModel']);
    }

    public function testGetMediaReturns404WhenNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/medias/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- GET /api/v1/medias ---

    public function testGetMediaCollectionReturnsMedias(): void
    {
        $this->createMedia('photo');

        $response = static::createClient()->request('GET', '/api/v1/medias', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertCount(1, $response->toArray());
    }

    public function testGetMediaCollectionFiltersByType(): void
    {
        $this->createMedia('photo');

        $response = static::createClient()->request('GET', '/api/v1/medias?type=video', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertCount(0, $response->toArray());
    }

    // --- GET /api/v1/medias/{id}/thumbnail ---

    public function testGetThumbnailReturns404WhenNoThumbnail(): void
    {
        $media = $this->createMedia(); // thumbnailPath est null

        static::createClient()->request('GET', '/api/v1/medias/'.$media->getId().'/thumbnail');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetThumbnailReturns404WhenMediaNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/medias/00000000-0000-0000-0000-000000000000/thumbnail');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- POST upload image → message dispatché ---

    public function testUploadImageDispatchesMediaProcessMessage(): void
    {
        $user = new User('uploader@example.com', 'Uploader');
        $this->em->persist($user);
        $this->em->flush();

        $tmp = tempnam(sys_get_temp_dir(), 'hc_img_');
        file_put_contents($tmp, 'fake-image-content');
        $uploadedFile = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $uploadedFile],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(1, $transport->get());
    }

    public function testUploadNonImageDoesNotDispatchMediaMessage(): void
    {
        $user = new User('uploader2@example.com', 'Uploader');
        $this->em->persist($user);
        $this->em->flush();

        $tmp = tempnam(sys_get_temp_dir(), 'hc_pdf_');
        file_put_contents($tmp, 'fake-pdf-content');
        $uploadedFile = new UploadedFile($tmp, 'doc.pdf', 'application/pdf', null, true);

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $uploadedFile],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get());
    }
}
