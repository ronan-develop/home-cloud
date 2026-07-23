<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\AlbumMedia;
use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\StorageServiceInterface;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels — Suppression de fichier via l'interface web.
 *
 * TDD RED : ces tests doivent d'abord échouer, puis passer après implémentation.
 */
final class FileDeleteWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
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
    }

    private function createUser(string $email = 'test@example.com', string $password = 'secret123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'test@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    private function createFolder(string $name, User $owner): Folder
    {
        $folder = new Folder($name, $owner);
        $this->em->persist($folder);
        $this->em->flush();

        return $folder;
    }

    private function createFile(string $name, Folder $folder, User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    private function csrfToken(string $folderId): string
    {
        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        return $crawler->filter('#delete-file-form input[name="_token"]')->attr('value');
    }

    public function testDeleteFileFlashesSuccessMessage(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $token = $this->csrfToken($folder->getId()->toRfc4122());

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => $token]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'supprimé');
    }

    public function testDeleteFileFlashesErrorMessageWhenStorageFails(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->client->disableReboot();
        $this->login();
        $token = $this->csrfToken($folder->getId()->toRfc4122());

        $failingStorage = new class implements StorageServiceInterface {
            public function store(\Symfony\Component\HttpFoundation\File\UploadedFile $file): array
            {
                throw new \RuntimeException('not used');
            }

            public function delete(string $relativePath): void
            {
                throw new \RuntimeException('Disque hors service');
            }

            public function getAbsolutePath(string $relativePath): string
            {
                throw new \RuntimeException('not used');
            }
        };
        static::getContainer()->set(\App\Service\StorageService::class, $failingStorage);

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => $token]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-error', 'suppression');

        // Le fichier ne doit pas avoir été supprimé de la base si le storage a échoué
        $found = $this->em->getRepository(File::class)->find($fileId);
        $this->assertNotNull($found, 'Le fichier ne doit pas être supprimé en base si le storage échoue');
    }


    public function testDeleteWithKeepInAlbumsPreservesMediaAndAlbumMedia(): void
    {
        $user = $this->createUser();
        $media = $this->createMediaFile($user, 'vacances.jpg', 'photo');
        $file = $media->getFile();
        $fileId = (string) $file->getId();
        $folderId = $file->getFolder()->getId()->toRfc4122();

        $album = new Album('Vacances', $user);
        $this->em->persist($album);
        $this->em->persist(new AlbumMedia($album, $media, 0));
        $this->em->flush();
        $mediaId = $media->getId();
        $this->em->clear();

        $this->login();
        $token = $this->csrfToken($folderId);

        $this->client->request('POST', '/files/' . $fileId . '/delete', [
            '_token' => $token,
            'folder_id' => $folderId,
            'keep_in_albums' => '1',
        ]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'conservé');

        $this->assertNull(
            $this->em->getRepository(File::class)->find($fileId),
            'Le File doit être supprimé de la base',
        );

        $survivingMedia = $this->em->getRepository(Media::class)->find($mediaId);
        $this->assertNotNull($survivingMedia, 'Le Media doit survivre au détachement');
        $this->assertNull($survivingMedia->getFile(), 'Le Media détaché ne doit plus avoir de File');

        $albumMediaCount = $this->em->getRepository(AlbumMedia::class)
            ->count(['media' => $mediaId]);
        $this->assertSame(1, $albumMediaCount, "L'appartenance à l'album doit survivre");
    }

    public function testDeleteWithoutKeepInAlbumsFlagRemovesMediaCompletely(): void
    {
        $user = $this->createUser();
        $media = $this->createMediaFile($user, 'sans-album.jpg', 'photo');
        $file = $media->getFile();
        $fileId = (string) $file->getId();
        $folderId = $file->getFolder()->getId()->toRfc4122();
        $mediaId = $media->getId();
        $this->em->clear();

        $this->login();
        $token = $this->csrfToken($folderId);

        $this->client->request('POST', '/files/' . $fileId . '/delete', [
            '_token' => $token,
            'folder_id' => $folderId,
        ]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'supprimé');
        $this->assertNull($this->em->getRepository(File::class)->find($fileId));
        $this->assertNull(
            $this->em->getRepository(Media::class)->find($mediaId),
            'Sans keep_in_albums, le Media doit disparaître comme avant (CASCADE)',
        );
    }

    public function testDeleteRejectsFileNotOwnedByUser(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $folder = $this->createFolder('Docs', $owner);
        $file = $this->createFile('secret.txt', $folder, $owner);
        $fileId = (string) $file->getId();

        // Le token CSRF de l'intention 'delete-file' ne dépend pas de la
        // ressource ciblée : on le lit via un fichier possédé par l'attaquant
        // dans son propre dossier, comme le ferait un navigateur légitime.
        $otherFolder = $this->createFolder('OtherDocs', $other);
        $this->createFile('decoy.txt', $otherFolder, $other);
        $this->em->clear();

        $this->login('other@example.com');
        $token = $this->csrfToken($otherFolder->getId()->toRfc4122());

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);
        $fileId = (string) $file->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => 'invalid-token']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteRequiresAuthentication(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);

        $this->client->request('POST', '/files/' . $file->getId() . '/delete', ['_token' => 'whatever']);

        $this->assertResponseRedirects('/login');
    }

    public function testExplorerRendersDeleteButtonWithInAlbumTrueWhenFileHasMediaInAlbum(): void
    {
        // Non-régression rendu HTML (#246) : file.id (objet Uuid) doit être
        // comparé correctement à filesInAlbum (strings RFC4122) côté Twig,
        // sinon le bouton reçoit toujours inAlbum=false et la modale
        // n'affiche jamais l'option "conserver dans mes albums".
        $user = $this->createUser();
        $media = $this->createMediaFile($user, 'vacances.jpg', 'photo');
        $file = $media->getFile();
        $folderId = $file->getFolder()->getId()->toRfc4122();

        $album = new Album('Vacances', $user);
        $this->em->persist($album);
        $this->em->persist(new AlbumMedia($album, $media, 0));
        $this->em->flush();
        $this->em->clear();

        $this->login();
        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        $this->assertResponseIsSuccessful();
        $button = $crawler->filter('[data-testid="delete-file-btn-' . $file->getId() . '"]');
        $this->assertStringContainsString('true)', $button->attr('onclick'));
    }

    public function testExplorerRendersDeleteButtonWithInAlbumFalseWhenFileHasNoMedia(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);

        $this->login();
        $crawler = $this->client->request('GET', '/explorer?folder=' . $folder->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $button = $crawler->filter('[data-testid="delete-file-btn-' . $file->getId() . '"]');
        $this->assertStringContainsString('false)', $button->attr('onclick'));
    }
}
