<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * #529 : page d'aide utilisateur (wiki simple, contenu statique) — HomeCloud
 * n'avait aucune documentation orientée utilisateur final, seulement de la
 * doc technique (README, .claude/*.md) destinée aux développeurs. /doc/aide
 * liste les sujets (sommaire), /doc/aide/{slug} affiche le contenu de
 * chacun — un seul controller par slug, pas un controller par sujet
 * (ajouter un sujet plus tard = une entrée, pas un nouveau fichier).
 */
final class HelpWebControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function login(string $email = 'test@example.com', string $password = 'pwd12345'): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/doc/aide');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexIsAccessibleToAuthenticatedUser(): void
    {
        $this->login();

        $this->client->request('GET', '/doc/aide');

        $this->assertResponseIsSuccessful();
    }

    // Sommaire : liste tous les sujets disponibles, chacun avec un lien vers
    // sa propre sous-page.
    public function testIndexListsAllTopicsWithLinksToTheirPage(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/doc/aide');

        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide/upload"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide/albums"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide/partage"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide/recherche"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide/takeout"]')->count());
    }

    public function testTopicPageDisplaysItsContent(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/doc/aide/upload');
        $this->assertResponseIsSuccessful();
        $text = strtolower($crawler->filter('body')->text());

        $this->assertStringContainsString('importer', $text);
    }

    public function testUnknownTopicReturns404(): void
    {
        $this->login();

        $this->client->request('GET', '/doc/aide/inexistant');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSidebarLinksToHelpIndex(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');

        $this->assertGreaterThan(0, $crawler->filter('a[href="/doc/aide"]')->count());
    }
}
