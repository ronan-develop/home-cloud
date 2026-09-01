<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\DirectMessage;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : page admin d'envoi d'un message direct 1-à-1 (#373).
 * Réservée à l'admin whitelisté (AdminVoter → BroadcastAdminChecker).
 */
final class DirectMessageAdminWebControllerTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM direct_messages');
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
        $this->client->request('GET', '/admin/direct-messages');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/direct-messages');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersFormForAdminUser(): void
    {
        $this->createAdmin();
        $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/direct-messages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testAdminCanSendDirectMessageToRecipient(): void
    {
        $this->createAdmin();
        $recipient = $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/direct-messages');
        $form = $crawler->selectButton('Envoyer')->form([
            'direct_message_form[recipient]' => (string) $recipient->getId(),
            'direct_message_form[subject]'   => 'Sujet du message',
            'direct_message_form[body]'      => 'Corps du message',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects();

        $message = $this->em->getRepository(DirectMessage::class)->findOneBy(['subject' => 'Sujet du message']);
        $this->assertNotNull($message);
        $this->assertSame('Corps du message', $message->getBody());
        $this->assertSame($recipient->getId()->toString(), $message->getRecipient()->getId()->toString());
    }

    public function testRejectsSubmissionWithoutSubject(): void
    {
        $this->createAdmin();
        $recipient = $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/direct-messages');
        $form = $crawler->selectButton('Envoyer')->form([
            'direct_message_form[recipient]' => (string) $recipient->getId(),
            'direct_message_form[subject]'   => '',
            'direct_message_form[body]'      => 'Corps du message',
        ]);
        $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.hc-broadcast-error');

        $count = (int) $this->em->getConnection()->executeQuery('SELECT COUNT(*) FROM direct_messages')->fetchOne();
        $this->assertSame(0, $count);
    }
}
