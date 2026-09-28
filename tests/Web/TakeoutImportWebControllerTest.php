<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Page d'import Google Photos Takeout (#327) — sélection du/des ZIP et
 * suivi de la progression (Stimulus + polling, testé côté JS séparément).
 */
final class TakeoutImportWebControllerTest extends WebTestCase
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

    public function testPageRequiresAuthentication(): void
    {
        $this->client->request('GET', '/import/takeout');

        $this->assertResponseRedirects('/login');
    }

    public function testPageIsAccessibleToAuthenticatedUser(): void
    {
        $this->login();

        $this->client->request('GET', '/import/takeout');

        $this->assertResponseIsSuccessful();
    }

    public function testPageHasFileInputAndSubmitButton(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/import/takeout');

        $this->assertGreaterThan(0, $crawler->filter('input[type="file"][accept=".zip"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('[data-takeout-import-target="submit"]')->count());
    }

    // #505 : mise en veille du PC pendant un upload = coupure de connexion
    // (constaté en conditions réelles le 2026-09-27) — même si la reprise
    // fonctionne (#491) et le message rassurant aussi (#492), le plus
    // simple reste d'éviter la coupure en désactivant la veille pour un
    // gros import.
    public function testPageRecommendsDisablingSleepForLargeImports(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/import/takeout');

        $this->assertStringContainsString('veille', strtolower($crawler->filter('body')->text()));
    }

    public function testSidebarLinksToTakeoutImportPage(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');

        $this->assertGreaterThan(0, $crawler->filter('a[href="/import/takeout"]')->count());
    }
}
