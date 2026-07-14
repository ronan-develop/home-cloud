<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Sécurité — XSS stocké via le nom d'un fichier uploadé (F9 de l'audit, volet entrée).
 *
 * Le rendu (folder-children.js) échappe désormais item.name, mais en défense en
 * profondeur, l'upload ne doit pas non plus stocker `<`/`>` bruts dans le nom —
 * contrairement au renommage/à la création de dossier (FilenameValidator, qui
 * REJETTE la requête), l'upload NEUTRALISE silencieusement ces caractères plutôt
 * que de refuser l'upload : le nom vient du disque de l'utilisateur, pas d'un champ
 * qu'il tape et peut corriger immédiatement.
 */
final class FileUploadFilenameXssTest extends WebTestCase
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

    public function testUploadedFilenameCannotContainAngleBrackets(): void
    {
        $this->createUser();
        $this->em->clear();
        $this->login();

        $tmp = tempnam(sys_get_temp_dir(), 'xss');
        file_put_contents($tmp, 'contenu');
        $upload = new UploadedFile($tmp, '<img src=x onerror=alert(1)>.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $upload]);
        @unlink($tmp);

        $stored = $this->em->getRepository(File::class)->findOneBy([]);

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<', $stored->getOriginalName());
        $this->assertStringNotContainsString('>', $stored->getOriginalName());
    }
}
