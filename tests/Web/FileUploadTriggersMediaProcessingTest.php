<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * FileWebController::upload() (route web classique, formulaire /files/upload)
 * ne dispatchait aucun MediaProcessMessage, contrairement à
 * FileUploadController (API, utilisé par l'explorateur JS). Un fichier
 * uploadé par cette route restait donc indéfiniment sans vignette, quel que
 * soit l'état du worker Messenger — 4 photos en prod en ont fait les frais
 * (cf. #251, rattrapées via app:media:process-missing).
 */
final class FileUploadTriggersMediaProcessingTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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

    private function createUser(string $email = 'test@example.com', string $password = 'pwd12345'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'test@example.com', string $password = 'pwd12345'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testMediaExistsImmediatelyAfterWebUpload(): void
    {
        $this->createUser();
        $this->em->clear();
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');
        $token = $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        $image = imagecreatetruecolor(200, 150);
        imagejpeg($image, $tmp);
        imagedestroy($image);
        $upload = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $token], ['file' => $upload]);
        @unlink($tmp);

        $file = $this->em->getRepository(File::class)->findOneBy([]);
        $this->assertNotNull($file);

        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);

        $this->assertNotNull(
            $media,
            'Le Media doit exister immédiatement après un upload via la route web, sans consommer la file Messenger'
        );
        $this->assertNotNull($media->getThumbnailPath(), 'La vignette doit être générée dans la foulée');
    }
}
