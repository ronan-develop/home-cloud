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

    public function testUploadExecutableFileIsRejected(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail('upload@example.com')
            ->setUsername('uploaduser');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/files/upload');
        $this->assertResponseIsSuccessful();

        // Créer un fichier temporaire .php
        $tmpDir = sys_get_temp_dir();
        $testFilePath = $tmpDir . '/test.php';
        file_put_contents($testFilePath, '<?php echo "hack";');
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'test.php',
            'application/x-php',
            null,
            true // test mode
        );

        // Soumettre le formulaire avec le fichier (même pattern que testFileUpload)
        $form = $crawler->selectButton('Envoyer')->form([
            'file_upload[file]' => $uploadedFile,
        ]);
        $client->submit($form);

        @unlink($testFilePath);

        // On doit être redirigé et avoir un message d'erreur
        $this->assertResponseRedirects('/files/upload');
        $client->followRedirect();
        $this->assertSelectorExists('.flash-danger');
        $this->assertSelectorTextContains('.flash-danger', 'interdit');

        // Vérifie qu'aucun fichier n'est créé en base
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fileRepo = $em->getRepository(File::class);
        $file = $fileRepo->findOneBy(['name' => 'test.php']);
        $this->assertNull($file, 'Aucun fichier exécutable ne doit être persisté');
    }

    public function testFileUpload(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        // Création d'un utilisateur de test
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail('upload@example.com')
            ->setUsername('uploaduser');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));
        // Persistance en base réelle pour login
        $em = $container->get('doctrine.orm.entity_manager');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/files/upload');
        $this->assertResponseIsSuccessful();

        // Création d'un vrai fichier temporaire à uploader avec le nom attendu
        $tmpDir = sys_get_temp_dir();
        $testFilePath = $tmpDir . '/test-file.txt';
        file_put_contents($testFilePath, 'Contenu test fichier');
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'test-file.txt',
            'text/plain',
            null,
            true // test mode
        );

        // Soumettre le formulaire avec le fichier
        $form = $crawler->selectButton('Envoyer')->form([
            'file_upload[file]' => $uploadedFile,
        ]);
        $client->submit($form);

        @unlink($testFilePath);

        // Vérifie la redirection et le flash message
        $this->assertResponseRedirects('/files/upload');
        $client->followRedirect();
        $this->assertSelectorExists('.flash-success');

        // Vérifie la persistance réelle en base
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fileRepo = $em->getRepository(File::class);
        $file = $fileRepo->findOneBy(['name' => 'test-file.txt']);
        $this->assertNotNull($file, 'Le fichier doit être persisté en base');
    }
}
