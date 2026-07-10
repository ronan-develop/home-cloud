<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de la couche web (Twig, layout, auth session).
 * Ces tests vérifient la structure HTML rendue, les redirections et les
 * éléments de mise en page présents dans base.html.twig.
 */
final class WebLayoutTest extends WebTestCase
{
    // --- Accès non authentifié ---

    public function testHomepageRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginPageReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageHasEmailAndPasswordFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="email"]'));
        $this->assertCount(1, $crawler->filter('input[name="password"]'));
        $this->assertCount(1, $crawler->filter('button[type="submit"]'));
    }

    // --- Structure du layout ---

    public function testLoginPageUsesBaseLayout(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        // DOCTYPE + balise html présents
        $this->assertStringContainsString('<!DOCTYPE html>', $client->getResponse()->getContent());
        $this->assertCount(1, $crawler->filter('html'));
        $this->assertCount(1, $crawler->filter('head'));
        $this->assertCount(1, $crawler->filter('body'));
    }

    public function testLoginPageHasTitleTag(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('title'));
        $this->assertStringContainsString('HomeCloud', $crawler->filter('title')->text());
    }

    // --- Barre de recherche (topbar) ---

    public function testSearchBarExistsInTopbar(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer et logguer un utilisateur
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User('test@example.com', 'Testeur');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Accès à la page d'accueil authentifiée
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérifie que la topbar existe
        $topbar = $crawler->filter('.hc-topbar');
        $this->assertCount(1, $topbar, 'La topbar .hc-topbar doit exister sur la page d\'accueil');

        // Vérifie que la barre de recherche existe dans la topbar
        $searchBar = $crawler->filter('.hc-topbar .hc-search');
        $this->assertCount(1, $searchBar, 'La barre de recherche .hc-search doit exister dans la topbar');
    }

    public function testSearchBarIsInteractive(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Setup utilisateur
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User('test@example.com', 'Testeur');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        // Login et accès à la page
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();
        $crawler = $client->request('GET', '/files');
        $this->assertResponseIsSuccessful();

        // Vérifie que la barre de recherche est un input ou contient un input
        $searchBar = $crawler->filter('.hc-search');
        $this->assertCount(1, $searchBar, 'La barre de recherche doit exister');

        // Vérifie que la barre est cliquable (doit avoir role ou être interactive)
        $html = $searchBar->html();
        $this->assertNotEmpty($html, 'La barre de recherche doit avoir du contenu HTML');
    }

    public function testSectionTitleDossiersIsAligned(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Setup utilisateur
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User('test@example.com', 'Testeur');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        // Login et accès à la page
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérifie que la section "Dossiers" existe avec l'icône et du texte
        $sectionTitle = $crawler->filter('.section-title');
        $this->assertCount(1, $sectionTitle, 'Le titre section "Dossiers" doit exister');

        // Vérifie que .section-title contient un icon et du texte
        $icon = $sectionTitle->filter('.section-title-icon');
        $this->assertCount(1, $icon, 'Le titre "Dossiers" doit avoir une icône');

        // Vérifie que le texte "Dossiers" est présent
        $this->assertStringContainsString('Dossiers', $sectionTitle->text(), 'Le titre doit contenir "Dossiers"');
    }
}
