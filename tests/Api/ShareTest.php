<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ShareTest extends AuthenticatedApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser(); // alice@example.com = owner JWT par défaut
    }

    private function createGuest(): User
    {
        return $this->createUser('bob@example.com', 'password123', 'Bob');
    }

    private function createFile(User $owner): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/test.jpg', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    private function createFolder(User $owner): Folder
    {
        $folder = new Folder('Shared Folder', $owner);
        $this->em->persist($folder);
        $this->em->flush();
        return $folder;
    }

    private function createAlbum(User $owner): Album
    {
        $album = new Album('Shared Album', $owner);
        $this->em->persist($album);
        $this->em->flush();
        return $album;
    }

    // ─── POST /api/v1/shares ────────────────────────────────────────────────

    public function testCreateShareFile(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'file',
                'resourceId'   => $file->getId()->toRfc4122(),
                'permission'   => 'read',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('file', $data['resourceType']);
        $this->assertSame('read', $data['permission']);
        $this->assertNull($data['expiresAt']);
    }

    public function testCreateShareWithExpiration(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $expiry = (new \DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i:s\Z');

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'file',
                'resourceId'   => $file->getId()->toRfc4122(),
                'permission'   => 'read',
                'expiresAt'    => $expiry,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotNull($response->toArray()['expiresAt']);
    }

    public function testCreateShareFolder(): void
    {
        $owner  = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest  = $this->createGuest();
        $folder = $this->createFolder($owner);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'folder',
                'resourceId'   => $folder->getId()->toRfc4122(),
                'permission'   => 'write',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateShareAlbum(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $album = $this->createAlbum($owner);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'album',
                'resourceId'   => $album->getId()->toRfc4122(),
                'permission'   => 'read',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateShareFailsIfGuestNotFound(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $file  = $this->createFile($owner);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => Uuid::v7()->toRfc4122(),
                'resourceType' => 'file',
                'resourceId'   => $file->getId()->toRfc4122(),
                'permission'   => 'read',
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateShareFailsIfInvalidResourceType(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'banana',
                'resourceId'   => $file->getId()->toRfc4122(),
                'permission'   => 'read',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateShareFailsIfInvalidPermission(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);

        $this->createAuthenticatedClient()->request('POST', '/api/v1/shares', [
            'json' => [
                'guestId'      => $guest->getId()->toRfc4122(),
                'resourceType' => 'file',
                'resourceId'   => $file->getId()->toRfc4122(),
                'permission'   => 'delete',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // ─── GET /api/v1/shares ─────────────────────────────────────────────────

    public function testGetCollectionAsOwner(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/shares', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertSame($share->getId()->toRfc4122(), $data[0]['id']);
    }

    public function testGetCollectionAsGuest(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        // Bob est guest, il doit aussi voir le partage
        $response = $this->createAuthenticatedClient('bob@example.com')->request('GET', '/api/v1/shares', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertCount(1, $response->toArray());
    }

    // ─── GET /api/v1/shares/{id} ────────────────────────────────────────────

    public function testGetShareById(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/shares/'.$share->getId()->toRfc4122(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame('file', $data['resourceType']);
        $this->assertSame('read', $data['permission']);
        $this->assertSame($guest->getId()->toRfc4122(), $data['guestId']);
        $this->assertSame($owner->getId()->toRfc4122(), $data['ownerId']);
    }

    public function testGetShareByIdForbiddenForOtherUser(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);

        $stranger = $this->createUser('stranger@example.com', 'password123', 'Stranger');
        $this->em->flush();

        $response = $this->createAuthenticatedClient('stranger@example.com')->request('GET', '/api/v1/shares/'.$share->getId()->toRfc4122(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── PATCH /api/v1/shares/{id} ──────────────────────────────────────────

    public function testPatchSharePermission(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient()->request('PATCH', '/api/v1/shares/'.$share->getId()->toRfc4122(), [
            'json'    => ['permission' => 'write'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('write', $response->toArray()['permission']);
    }

    public function testPatchShareForbiddenForGuest(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient('bob@example.com')->request('PATCH', '/api/v1/shares/'.$share->getId()->toRfc4122(), [
            'json'    => ['permission' => 'write'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── DELETE /api/v1/shares/{id} ─────────────────────────────────────────

    public function testDeleteShare(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/shares/'.$share->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteShareForbiddenForGuest(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $this->createAuthenticatedClient('bob@example.com')->request('DELETE', '/api/v1/shares/'.$share->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── Accès aux ressources partagées ─────────────────────────────────────

    public function testGuestCanAccessSharedFile(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient('bob@example.com')->request('GET', '/api/v1/files/'.$file->getId()->toRfc4122(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function testGuestCannotAccessFileWithoutShare(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $this->em->flush();

        $response = $this->createAuthenticatedClient('bob@example.com')->request('GET', '/api/v1/files/'.$file->getId()->toRfc4122(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testExpiredShareDeniesAccess(): void
    {
        $owner = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $guest = $this->createGuest();
        $file  = $this->createFile($owner);
        $share = new Share($owner, $guest, 'file', $file->getId(), 'read', new \DateTimeImmutable('-1 day'));
        $this->em->persist($share);
        $this->em->flush();

        $response = $this->createAuthenticatedClient('bob@example.com')->request('GET', '/api/v1/files/'.$file->getId()->toRfc4122(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
