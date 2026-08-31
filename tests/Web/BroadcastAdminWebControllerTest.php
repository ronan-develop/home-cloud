<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : interface admin web du broadcast (#283). Réservée au
 * compte configuré comme admin (BROADCAST_ADMIN_EMAIL, cf. .env.test) — pas
 * de ROLE_ADMIN Symfony, juste une whitelist explicite via
 * BroadcastAdminChecker, car aucun rôle différencié n'existe dans ce projet.
 */
final class BroadcastAdminWebControllerTest extends WebTestCase
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

    private function createAdmin(): User
    {
        return $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/broadcast');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/broadcast');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersFormForAdminUser(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/broadcast');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="broadcast_message_form[alsoInApp]"]');
    }

    public function testSubmittingWithAlsoInAppCreatesLocalBroadcastMessage(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $this->em->getConnection()->executeStatement('DELETE FROM broadcast_messages');

        $crawler = $this->client->request('GET', '/admin/broadcast');
        $form = $crawler->selectButton('Envoyer')->form([
            'broadcast_message_form[subject]' => 'Maintenance planifiée',
            'broadcast_message_form[body]'    => 'Corps du message',
        ]);
        $form['broadcast_message_form[alsoInApp]']->tick();

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();

        $count = (int) $this->em->getConnection()->executeQuery('SELECT COUNT(*) FROM broadcast_messages')->fetchOne();
        $this->assertSame(1, $count);
    }

    public function testSubmittingWithoutAlsoInAppDoesNotCreateBroadcastMessage(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $this->em->getConnection()->executeStatement('DELETE FROM broadcast_messages');

        $crawler = $this->client->request('GET', '/admin/broadcast');
        $form = $crawler->selectButton('Envoyer')->form([
            'broadcast_message_form[subject]' => 'Maintenance planifiée',
            'broadcast_message_form[body]'    => 'Corps du message',
            'broadcast_message_form[dryRun]'  => true,
        ]);

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();

        $count = (int) $this->em->getConnection()->executeQuery('SELECT COUNT(*) FROM broadcast_messages')->fetchOne();
        $this->assertSame(0, $count);
    }
}
