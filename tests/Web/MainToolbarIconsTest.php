<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Vérifie que la modale de résultats de recherche (composant MainToolbar)
 * utilise les icônes SVG Heroicons du design system plutôt que des emojis.
 * TDD RED → GREEN
 */
final class MainToolbarIconsTest extends WebTestCase
{
    public function testSearchResultsUseHeroiconsNotEmojis(): void
    {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);

        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user   = new User('icons@example.com', 'Icons');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => 'icons@example.com',
            'password' => 'secret123',
        ]);
        $client->submit($form);
        $client->followRedirect();

        $client->request('GET', '/gallery');
        $html = $client->getResponse()->getContent();

        $start = strpos($html, 'id="search-results-overlay"');
        $end   = strpos($html, '</script>', $start);
        $this->assertNotFalse($start, 'Le composant #search-results-overlay doit être rendu dans la page.');
        $component = substr($html, $start, $end - $start);

        $emojis = ['📁', '🖼️', '🎬', '🎵', '📄', '📎'];
        foreach ($emojis as $emoji) {
            $this->assertStringNotContainsString($emoji, $component, sprintf('L\'emoji "%s" ne doit plus apparaître dans le composant de recherche.', $emoji));
        }

        $this->assertStringContainsString('hc-icon-folder', $component);
        $this->assertStringContainsString('hc-icon-images', $component);
        $this->assertStringContainsString('hc-icon-video', $component);
        $this->assertStringContainsString('hc-icon-music', $component);
        $this->assertStringContainsString('hc-icon-file', $component);
    }
}
