<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de l'explorateur de fichiers web sur la route /explorer.
 * Migré de FileExplorerTest pour la séparation home/explorer.
 */
final class ExplorerPageTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'explorer@example.com', string $password = 'secret123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Explorer');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'explorer@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);

        // Après submit, vérifier si on est redirigé (status 302)
        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
    }

    // --- Route /explorer ---

    public function testExplorerRouteShowsFileExplorer(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="file-explorer"]');
    }

    public function testExplorerRouteShowsUploadZone(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="upload-zone"]');
    }

    public function testExplorerSectionTitleIsAligned(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.section-title');
    }

    // --- Upload redirects to /explorer ---

    public function testUploadFileRedirectsToExplorer(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $uploaded]);
        $this->assertResponseRedirects('/explorer');
    }

    public function testUploadedFileAppearsInExplorerList(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);
        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test-visible.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId], ['file' => $uploaded]);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-testid="file-list"]', 'test-visible.txt');
    }

    // --- Delete redirects to /explorer ---

    public function testDeleteFileRedirectsToExplorer(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);

        $file = new \App\Entity\File('delete-me.txt', 'text/plain', 10, 'test/path.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $fileId = $file->getId()->toRfc4122();

        $this->login();

        $this->client->request('POST', "/files/{$fileId}/delete");
        $this->assertResponseRedirects('/explorer');
    }

    public function testDeleteFileNotOwnedReturns403(): void
    {
        $owner = $this->createUser('owner@example.com');

        $folder = new Folder('Uploads', $owner);
        $this->em->persist($folder);
        $file = new \App\Entity\File('private.txt', 'text/plain', 10, 'test/private.txt', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        $this->createUser('attacker@example.com', 'secret123');
        $this->login('attacker@example.com');
        $this->client->request('POST', "/files/{$file->getId()->toRfc4122()}/delete");
        $this->assertResponseStatusCodeSame(403);
    }

    // --- Layout tests (migré de WebLayoutTest) ---

    public function testSectionTitleDossiersIsAligned(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $sectionTitle = $this->client->getCrawler()->filter('.section-title');
        $this->assertCount(1, $sectionTitle, 'Le titre section "Dossiers" doit exister');

        $icon = $sectionTitle->filter('.section-title-icon');
        $this->assertCount(1, $icon, 'Le titre "Dossiers" doit avoir une icône');

        $this->assertStringContainsString('Dossiers', $sectionTitle->text(), 'Le titre doit contenir "Dossiers"');
    }

    public function testBreadcrumbsAreDisplayed(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $breadcrumbs = $this->client->getCrawler()->filter('.main-breadcrumbs');
        $this->assertCount(1, $breadcrumbs, 'Les breadcrumbs doivent être affichées');
    }

    public function testNoDoubloSectionTitle(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $sectionTitles = $this->client->getCrawler()->filter('.section-title');
        $this->assertCount(1, $sectionTitles, 'Il ne doit avoir qu\'une seule section-title "Dossiers"');
    }

    public function testImportCardUsesCloudIcon(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();

        $importCardHtml = $this->client->getCrawler()->filter('.import-card')->html() ?: '';
        $this->assertStringNotContainsString('☁️', $importCardHtml, 'Pas d\'emoji ☁️ dans import-card — doit être remplacé par SVG');

        $cloudIcons = $this->client->getCrawler()->filter('.import-card svg.hc-icon-cloud');
        $this->assertGreaterThanOrEqual(1, $cloudIcons->count(), 'ImportCard doit avoir icône cloud SVG');
    }
}
