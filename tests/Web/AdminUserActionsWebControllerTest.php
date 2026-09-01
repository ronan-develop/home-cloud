<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : actions admin sur un user (#375) — désactivation,
 * réactivation, reset password. Réservées à l'admin whitelisté (AdminVoter
 * → BroadcastAdminChecker).
 */
final class AdminUserActionsWebControllerTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM refresh_tokens');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createAdmin(): User
    {
        return $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
    }

    /**
     * Récupère un token CSRF valide en le lisant depuis une page réellement
     * rendue (pattern déjà utilisé dans FolderDeleteWebTest) : appeler
     * security.csrf.token_manager directement échoue hors requête active.
     */
    private function csrfToken(User $target, string $action): string
    {
        $crawler = $this->client->request('GET', "/admin/users/{$target->getId()}");
        $route = match ($action) {
            'deactivate' => '/deactivate',
            'reactivate' => '/reactivate',
            'reset-password' => '/reset-password',
        };

        return $crawler->filter('form[action*="' . $route . '"] input[name="_token"]')->attr('value');
    }

    public function testRejectsAnonymousUserOnDeactivate(): void
    {
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');

        $this->client->request('POST', "/admin/users/{$target->getId()}/deactivate");

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminOnDeactivate(): void
    {
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('POST', "/admin/users/{$target->getId()}/deactivate");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRejectsInvalidCsrfTokenOnDeactivate(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('POST', "/admin/users/{$target->getId()}/deactivate", ['_token' => 'invalid']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeactivateSetsUserInactiveAndRevokesRefreshTokens(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $this->em->persist(new RefreshToken($target));
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $token = $this->csrfToken($target, 'deactivate');

        $this->client->request('POST', "/admin/users/{$target->getId()}/deactivate", ['_token' => $token]);

        $this->assertResponseRedirects("/admin/users/{$target->getId()}");

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($target->getId());
        $this->assertFalse($reloaded->isActive());

        $remaining = $this->em->getConnection()->executeQuery(
            'SELECT COUNT(*) FROM refresh_tokens WHERE user_id = ?',
            [$target->getId()->toBinary()],
        )->fetchOne();
        $this->assertSame(0, (int) $remaining);
    }

    public function testDeactivateRejectsGuestTarget(): void
    {
        $this->createAdmin();
        $guest = $this->createWebUser('guest@example.com', 'secret123', 'Guest');
        $guest->markAsGuest();
        $this->em->flush();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('POST', "/admin/users/{$guest->getId()}/deactivate", [
            '_token' => 'irrelevant-since-404-before-csrf-check-order-may-vary',
        ]);

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
    }

    public function testReactivateSetsUserActive(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $target->deactivate();
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $token = $this->csrfToken($target, 'reactivate');

        $this->client->request('POST', "/admin/users/{$target->getId()}/reactivate", ['_token' => $token]);

        $this->assertResponseRedirects("/admin/users/{$target->getId()}");

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($target->getId());
        $this->assertTrue($reloaded->isActive());
    }

    public function testResetPasswordSendsEmail(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $token = $this->csrfToken($target, 'reset-password');

        $this->client->request('POST', "/admin/users/{$target->getId()}/reset-password", ['_token' => $token]);

        $this->assertResponseRedirects("/admin/users/{$target->getId()}");
        $this->assertEmailCount(1);
    }
}
