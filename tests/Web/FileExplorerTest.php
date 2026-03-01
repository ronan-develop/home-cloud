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
 * Tests fonctionnels de l'explorateur de fichiers web (Phase 7C).
 * Couvre : affichage, upload, suppression, navigation dossiers.
 */
final class FileExplorerTest extends WebTestCase
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
        $this->client->followRedirect();
    }

    // --- Affichage ---

    public function testHomepageShowsFileExplorer(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="file-explorer"]');
    }

    public function testHomepageShowsUploadZone(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="upload-zone"]');
    }

    // --- Upload ---

    public function testUploadFileRedirectsToHome(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $uploaded]);
        $this->assertResponseRedirects('/');
    }

    public function testUploadedFileAppearsInList(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test-visible.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $uploaded]);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-testid="file-list"]', 'test-visible.txt');
    }

    public function testUploadBlockedExtensionReturns400(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.php';
        file_put_contents($tmpFile, '<?php echo "hack"; ?>');
        $uploaded = new UploadedFile($tmpFile, 'hack.php', 'application/x-php', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $uploaded]);
        $this->assertResponseStatusCodeSame(400);
    }

    // --- Suppression ---

    public function testDeleteFileRedirectsToHome(): void
    {
        $user = $this->createUser();

        // Créer un dossier + fichier AVANT login (user encore attaché)
        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);

        $file = new \App\Entity\File('delete-me.txt', 'text/plain', 10, 'test/path.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $fileId = $file->getId()->toRfc4122();

        $this->login();

        $this->client->request('POST', "/files/{$fileId}/delete");
        $this->assertResponseRedirects('/');
    }

    public function testDeleteFileNotOwnedReturns403(): void
    {
        $owner = $this->createUser('owner@example.com');

        // Fichier appartient à owner — créer AVANT login attacker
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
}
