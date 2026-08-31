<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Album;
use App\Entity\AlbumMedia;
use App\Entity\File;
use App\Entity\Media;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels pour DELETE /api/v1/files/{id}.
 */
final class FileDeleteTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    private function createFile(string $name, \App\Entity\Folder $folder, \App\Entity\User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    private function createMediaFile(User $owner, string $name = 'photo.jpg'): Media
    {
        $folder = $this->createFolder('Photos', $owner);
        $file = new File($name, 'image/jpeg', 1024, 'test/' . uniqid() . '.jpg', $folder, $owner, false);
        $this->em->persist($file);

        $media = new Media($file, 'photo');
        $media->setWidth(1920);
        $media->setHeight(1080);
        $media->setThumbnailPath('thumbs/' . $name . '.thumb.jpg');
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    /** Le propriétaire peut supprimer son fichier → 204 */
    public function testDeleteFileByOwnerReturns204(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Docs', $alice);
        $file   = $this->createFile('document.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('DELETE', '/api/v1/files/' . $file->getId());

        static::assertResponseStatusCodeSame(204);

        $this->em->clear();
        $deleted = $this->em->getRepository(File::class)->find($file->getId());
        $this->assertNull($deleted, 'Le fichier doit être supprimé de la base');
    }

    /** Un autre utilisateur ne peut pas supprimer le fichier → 403 */
    public function testDeleteFileByOtherUserForbidden(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $bob    = $this->createUser('bob@example.com', 'password123', 'Bob');
        $folder = $this->createFolder('AliceFolder', $alice);
        $file   = $this->createFile('secret.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($bob);
        $client->request('DELETE', '/api/v1/files/' . $file->getId());

        static::assertResponseStatusCodeSame(403);
    }

    /** Fichier inexistant → 404 */
    public function testDeleteNonExistentFileReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $client = $this->createAuthenticatedClient($alice);
        $client->request('DELETE', '/api/v1/files/123e4567-e89b-12d3-a456-426614174000');

        static::assertResponseStatusCodeSame(404);
    }

    /** ?keepInAlbums=1 sur un File avec Media doit détacher au lieu de tout supprimer (aligné sur #246 côté web) */
    public function testDeleteWithKeepInAlbumsPreservesMediaAndAlbumMedia(): void
    {
        $alice = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $media = $this->createMediaFile($alice, 'vacances.jpg');
        $file  = $media->getFile();
        $fileId = (string) $file->getId();

        $album = new Album('Vacances', $alice);
        $this->em->persist($album);
        $this->em->persist(new AlbumMedia($album, $media, 0));
        $this->em->flush();
        $mediaId = $media->getId();
        $this->em->clear();

        $client = $this->createAuthenticatedClient('alice@example.com');
        $client->request('DELETE', '/api/v1/files/' . $fileId . '?keepInAlbums=1');

        static::assertResponseStatusCodeSame(204);

        $this->assertNull(
            $this->em->getRepository(File::class)->find($fileId),
            'Le File doit être supprimé de la base',
        );

        $survivingMedia = $this->em->getRepository(Media::class)->find($mediaId);
        $this->assertNotNull($survivingMedia, 'Le Media doit survivre au détachement');
        $this->assertNull($survivingMedia->getFile(), 'Le Media détaché ne doit plus avoir de File');

        $albumMediaCount = $this->em->getRepository(AlbumMedia::class)->count(['media' => $mediaId]);
        $this->assertSame(1, $albumMediaCount, "L'appartenance à l'album doit survivre");
    }

    /** Sans keepInAlbums, comportement historique inchangé : Media supprimé avec le File */
    public function testDeleteWithoutKeepInAlbumsFlagRemovesMediaCompletely(): void
    {
        $alice = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $media = $this->createMediaFile($alice, 'sans-album.jpg');
        $file  = $media->getFile();
        $fileId = (string) $file->getId();
        $mediaId = $media->getId();
        $this->em->clear();

        $client = $this->createAuthenticatedClient('alice@example.com');
        $client->request('DELETE', '/api/v1/files/' . $fileId);

        static::assertResponseStatusCodeSame(204);
        $this->assertNull($this->em->getRepository(File::class)->find($fileId));
        $this->assertNull(
            $this->em->getRepository(Media::class)->find($mediaId),
            'Sans keepInAlbums, le Media doit disparaître comme avant',
        );
    }

    /** keepInAlbums=1 sur un File sans Media : suppression simple, pas d'erreur */
    public function testDeleteWithKeepInAlbumsButNoMediaBehavesLikeSimpleDelete(): void
    {
        $alice  = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Docs', $alice);
        $file   = $this->createFile('document.txt', $folder, $alice);
        $fileId = (string) $file->getId();

        $client = $this->createAuthenticatedClient($alice);
        $client->request('DELETE', '/api/v1/files/' . $fileId . '?keepInAlbums=1');

        static::assertResponseStatusCodeSame(204);
        $this->assertNull($this->em->getRepository(File::class)->find($fileId));
    }
}
