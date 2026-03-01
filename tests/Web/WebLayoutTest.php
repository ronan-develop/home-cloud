<?php

declare(strict_types=1);

namespace App\Tests\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
