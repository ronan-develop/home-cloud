<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de l'authentification web (session Symfony).
 * Vérifie le cycle complet : login → session → accès protégé → logout.
 */
final class WebAuthTest extends WebTestCase
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

    private function createUser(
        string $email = 'web@example.com',
        string $password = 'secret123',
        string $displayName = 'WebUser',
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, $displayName);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // --- Login valide ---

    /** Soumet le formulaire de login via le crawler (gère le token CSRF automatiquement). */
    private function login(string $email = 'web@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
    }

    public function testLoginWithValidCredentialsRedirectsToDashboard(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseRedirects('/');
    }

    public function testAfterLoginDashboardIsAccessible(): void
    {
        $this->createUser();
        $this->login();
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav');
        $this->assertSelectorExists('aside');
    }

    public function testDashboardShowsDisplayName(): void
    {
        $this->createUser('web@example.com', 'secret123', 'Alice Dupont');
        $this->login('web@example.com', 'secret123');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav', 'Alice Dupont');
    }

    // --- Flash messages ---

    public function testLoginSuccessShowsWelcomeFlash(): void
    {
        $this->createUser('web@example.com', 'secret123', 'Alice Dupont');
        $this->login('web@example.com', 'secret123');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.flash-success');
        $this->assertSelectorTextContains('.flash-success', 'Alice');
    }

    public function testLoginFailureShowsErrorFlash(): void
    {
        $this->createUser();
        $this->login('web@example.com', 'mauvais_mot_de_passe');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
        $this->assertSelectorExists('.flash-error');
    }

    // --- Login invalide ---

    public function testLoginWithWrongPasswordStaysOnLoginPage(): void
    {
        $this->createUser();
        $this->login('web@example.com', 'mauvais_mot_de_passe');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
    }

    public function testLoginWithUnknownEmailStaysOnLoginPage(): void
    {
        $this->login('inconnu@example.com', 'nimporte');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('app_login');
    }

    // --- Logout ---

    public function testLogoutRedirectsToLogin(): void
    {
        $this->createUser();
        $this->login();
        $this->client->followRedirect();

        $this->client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');
    }

    public function testAfterLogoutDashboardRedirectsToLogin(): void
    {
        $this->createUser();
        $this->login();
        $this->client->followRedirect();

        $this->client->request('GET', '/logout');
        $this->client->followRedirect(); // → /login

        $this->client->request('GET', '/');
        $this->assertResponseRedirects('/login');
    }
}
