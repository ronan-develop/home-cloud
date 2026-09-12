<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : marquage discret de lastChangelogViewedAt au clic sur
 * une entrée changelog du dropdown de notifications (#411) — même effet que
 * visiter /changelog, sans re-render la page (l'utilisateur ouvre la PR
 * GitHub dans un nouvel onglet, cette route se joue en fond dans l'onglet
 * d'origine pour permettre l'animation de retrait).
 */
final class ChangelogMarkViewedWebControllerTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('POST', '/changelog/mark-viewed');

        $this->assertResponseRedirects('/login');
    }

    public function testUpdatesLastChangelogViewedAtForAuthenticatedUser(): void
    {
        $user = $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs('user@example.com');

        $this->assertNull($user->getLastChangelogViewedAt());

        $this->client->request('POST', '/changelog/mark-viewed');

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($reloaded->getLastChangelogViewedAt());
    }
}
