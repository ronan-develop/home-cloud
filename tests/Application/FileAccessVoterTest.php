<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FileAccessVoterTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
    }

    private function createUser(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $username, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();
        return $user;
    }

    public function testOwnerCanDownloadAndDelete(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Suppression de tout utilisateur 'owner' existant pour éviter l'erreur d'unicité
        $existingOwner = $em->getRepository(User::class)->findOneBy(['username' => 'owner']);
        if ($existingOwner) {
            $em->remove($existingOwner);
            $em->flush();
        }
        $owner = $this->createUser($em, $hasher, 'owner');
        $client->loginUser($owner);

        // Upload d'un fichier par l'owner
        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, 'file.txt', 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        file_put_contents('/tmp/test_owner_upload.html', $client->getResponse()->getContent());
        @unlink($tmpFile);
        $em->clear();
        $ownerReloaded = $em->getRepository(User::class)->findOneBy(['username' => 'owner']);
        $files = $em->getRepository(File::class)->findAll();
        file_put_contents('/tmp/test_owner_files.txt', print_r($files, true));
        $file = $em->getRepository(File::class)->findOneBy([
            'name' => 'file.txt',
            'owner' => $ownerReloaded
        ]);
        $this->assertNotNull($file);

        // Owner peut télécharger
        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseIsSuccessful();
        // Owner peut supprimer
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseRedirects('/files/upload');
    }

    public function testAdminCanDownloadAndDelete(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $owner = $this->createUser($em, $hasher, 'owner2');
        $admin = $this->createUser($em, $hasher, 'admin', ['ROLE_ADMIN']);
        // Upload par owner
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file2.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, 'file2.txt', 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        file_put_contents('/tmp/test_admin_upload.html', $client->getResponse()->getContent());
        @unlink($tmpFile);
        $em->clear();
        $ownerReloaded = $em->getRepository(User::class)->findOneBy(['username' => 'owner2']);
        $files = $em->getRepository(File::class)->findAll();
        file_put_contents('/tmp/test_admin_files.txt', print_r($files, true));
        $file = $em->getRepository(File::class)->findOneBy([
            'name' => 'file2.txt',
            'owner' => $ownerReloaded
        ]);
        $this->assertNotNull($file);

        // Admin peut télécharger
        $client->loginUser($admin);
        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseIsSuccessful();
        // Admin peut supprimer
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseRedirects('/files/upload');
    }

    public function testStrangerCannotDownloadOrDelete(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $owner = $this->createUser($em, $hasher, 'owner3');
        $stranger = $this->createUser($em, $hasher, 'stranger');
        // Upload par owner
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file3.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, 'file3.txt', 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        file_put_contents('/tmp/test_stranger_upload.html', $client->getResponse()->getContent());
        @unlink($tmpFile);
        $em->clear();
        $ownerReloaded = $em->getRepository(User::class)->findOneBy(['username' => 'owner3']);
        $files = $em->getRepository(File::class)->findAll();
        file_put_contents('/tmp/test_stranger_files.txt', print_r($files, true));
        $file = $em->getRepository(File::class)->findOneBy([
            'name' => 'file3.txt',
            'owner' => $ownerReloaded
        ]);
        $this->assertNotNull($file);

        // Stranger ne peut pas télécharger
        $client->loginUser($stranger);
        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseStatusCodeSame(403);
        // Stranger ne peut pas supprimer
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
