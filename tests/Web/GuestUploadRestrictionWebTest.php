<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Un compte invité (accountType=guest) est exclusivement lecture/téléchargement :
 * pas d'upload, quelle que soit la permission du Share qu'il a reçu.
 */
final class GuestUploadRestrictionWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createGuestWithPassword(string $email, string $password): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $guest = new User($email, 'Guest');
        $guest->markAsGuest();
        $guest->setPassword($hasher->hashPassword($guest, $password));
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    private function login(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testGuestCannotUploadAFile(): void
    {
        $this->createGuestWithPassword('guest-upload@example.com', 'secret123');
        $this->login('guest-upload@example.com', 'secret123');

        $crawler = $this->client->request('GET', '/explorer');
        $token = $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'guest_upload');
        file_put_contents($tmp, 'contenu');
        $upload = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $token], ['file' => $upload]);
        @unlink($tmp);

        $this->assertResponseStatusCodeSame(403);
    }
}
