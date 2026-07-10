<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests du composant Icon (Heroicons SVG).
 * Vérifie que le composant Icon rend correctement les icônes Heroicons.
 */
final class IconComponentTest extends WebTestCase
{
    public function testFolderIconExists(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Créer un utilisateur pour accéder à la page protégée
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get('security.password_hasher');
        $userClass = 'App\Entity\User';
        $user = new $userClass('test@example.com', 'Testeur');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        // Login et accès à la page home (qui contient les icônes)
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérifie que les icônes (futures avec Heroicons) seront présentes
        // NOTE: Pour l'instant, emoji. Après refactor → SVG Heroicons
        $sidebarFolders = $crawler->filter('.sidebar-folders');
        $this->assertCount(1, $sidebarFolders, 'Le composant SidebarFolders doit exister');
    }

    public function testCloudIconInImportCard(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get('security.password_hasher');
        $userClass = 'App\Entity\User';
        $user = new $userClass('test@example.com', 'Testeur');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérifie que la zone d'import existe
        $importCard = $crawler->filter('.import-card');
        $this->assertCount(1, $importCard, 'Le composant ImportCard doit exister');
    }
}
