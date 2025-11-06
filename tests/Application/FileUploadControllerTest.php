<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FileUploadControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset complet base + fixtures (pattern IA Home Cloud)
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
    }

    public function testFileUpload(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        // Création d'un utilisateur de test
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail('upload@example.com')
            ->setUsername('uploaduser');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));
        $em->persist($user);
        $em->flush();

        // Authentification du user
        $client->loginUser($user);

        $crawler = $client->request('GET', '/files/upload');
        $this->assertResponseIsSuccessful();

        // Création d'un vrai fichier temporaire à uploader

        // Créer un vrai fichier temporaire nommé test.txt
        $tmpDir = sys_get_temp_dir();
        $testFilePath = $tmpDir . '/test.txt';
        file_put_contents($testFilePath, 'Contenu de test');
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'test.txt',
            'text/plain',
            null,
            true // test mode
        );

        $client->submitForm('Envoyer', [
            'file_upload[file]' => $uploadedFile,
        ]);

        // Nettoyage du fichier temporaire
        @unlink($testFilePath);

        // Vérifie la redirection et le flash message
        $this->assertResponseRedirects('/files/upload');
        $client->followRedirect();
        $this->assertSelectorExists('.flash-success');

        // Vérifie la persistance en base
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fileRepo = $em->getRepository(File::class);
        $files = $fileRepo->findAll();
        fwrite(STDERR, "\n--- Fichiers en base après upload ---\n");
        foreach ($files as $f) {
            fwrite(STDERR, sprintf("- name: %s | path: %s | size: %d | mime: %s\n", $f->getName(), $f->getPath(), $f->getSize(), $f->getMimeType()));
        }
        $file = $fileRepo->findOneBy(['name' => 'test.txt']);
        $this->assertNotNull($file, 'Le fichier doit être persisté en base');
        $this->assertEquals('test.txt', $file->getName());
        $this->assertContains($file->getMimeType(), ['text/plain', 'application/octet-stream']);
        $this->assertGreaterThan(0, $file->getSize());
        $this->assertFileExists($file->getPath());
    }
}
