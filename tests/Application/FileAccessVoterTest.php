<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\User;

class FileAccessVoterTest extends WebTestCase
{
    private function createUser($em, $hasher, $username, $roles = []): User
    {
        $user = (new User())
            ->setEmail($username . uniqid() . '@example.com')
            ->setUsername($username . uniqid());
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
        $hasher = $container->get('security.user_password_hasher');

        $owner = $this->createUser($em, $hasher, 'owner_test');
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file_' . uniqid() . '.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, basename($namedFile), 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        @unlink($tmpFile);
        @unlink($namedFile);
        $em->clear();
        $file = $em->getRepository(\App\Entity\File::class)->findOneBy(['name' => basename($namedFile)]);
        $this->assertNotNull($file);

        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseIsSuccessful();
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseRedirects('/files/upload');
    }

    public function testAdminCanDownloadAndDelete(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get('security.user_password_hasher');

        $owner = $this->createUser($em, $hasher, 'owner2_test');
        $admin = $this->createUser($em, $hasher, 'admin_test', ['ROLE_ADMIN']);
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file2_' . uniqid() . '.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, basename($namedFile), 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        @unlink($tmpFile);
        @unlink($namedFile);
        $em->clear();
        $file = $em->getRepository(\App\Entity\File::class)->findOneBy(['name' => basename($namedFile)]);
        $this->assertNotNull($file);

        $client->loginUser($admin);
        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseIsSuccessful();
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseRedirects('/files/upload');
    }

    public function testStrangerCannotDownloadOrDelete(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get('security.user_password_hasher');

        $owner = $this->createUser($em, $hasher, 'owner3_test');
        $stranger = $this->createUser($em, $hasher, 'stranger_test');
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/files/upload');
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'data');
        $namedFile = sys_get_temp_dir() . '/file3_' . uniqid() . '.txt';
        copy($tmpFile, $namedFile);
        $uploadedFile = new UploadedFile($namedFile, basename($namedFile), 'text/plain', null, true);
        $client->submitForm('Envoyer', ['file_upload[file]' => $uploadedFile]);
        @unlink($tmpFile);
        @unlink($namedFile);
        $em->clear();
        $file = $em->getRepository(\App\Entity\File::class)->findOneBy(['name' => basename($namedFile)]);
        $this->assertNotNull($file);

        $client->loginUser($stranger);
        $client->request('GET', '/files/download/' . $file->getId());
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/files/delete/' . $file->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
