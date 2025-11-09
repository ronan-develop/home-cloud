<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FileControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset complet base + fixtures (pattern IA Home Cloud)
    }

    public function testDownloadZipNominal(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Création d'un utilisateur et d'un fichier
        $user = (new User())
            ->setEmail('zipuser@example.com')
            ->setUsername('zipuser');
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // Création physique du fichier pour le ZIP
        $testFilePath = '/tmp/test.txt';
        file_put_contents($testFilePath, 'Contenu de test');
        $em->getConnection()->executeStatement("INSERT INTO file (name, path, size, mime_type, uploaded_at, hash, owner_id) VALUES ('test.txt', '{$testFilePath}', 15, 'text/plain', NOW(), 'hash', {$user->getId()})");

        $crawler = $client->request('GET', '/files/download-zip');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('application/zip', $client->getResponse()->headers->get('content-type'));
        // Nettoyage du fichier temporaire
        @unlink($testFilePath);
    }

    public function testDownloadZipThrowsNoFilesFoundException(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Création d'un utilisateur sans fichier
        $user = (new User())
            ->setEmail('nofileuser@example.com')
            ->setUsername('nofileuser');
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // Appel de la route qui doit provoquer la redirection
        $client->request('GET', '/files/download-zip');
        $this->assertResponseRedirects('/files/upload');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash-danger', 'Aucun fichier à archiver');
    }
}
