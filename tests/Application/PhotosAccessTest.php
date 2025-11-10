<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PhotosAccessTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset complet base + fixtures (pattern obligatoire API Platform)
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        // Pas de création d'utilisateur ici
    }

    public function testPhotosPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/photos');
        $this->assertResponseRedirects('/login');
    }

    public function testPhotosPageAccessibleWhenAuthenticated(): void
    {
        // Création de l'utilisateur admin
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $userClass = 'App\\Entity\\User';
        $user = new $userClass();
        $user->setUsername('admin');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('$2y$13$a0scOYGb58lA.D1wSAKr3ubI8AMBwUlwuhX5IaMU91K3UyP4HmA6G');
        $entityManager->persist($user);
        $entityManager->flush();

        // Simule un login via le formulaire
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'admin',
            '_password' => 'root',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Accès à la page protégée
        $client->request('GET', '/photos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }
}
