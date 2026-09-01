<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\DirectMessage;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : marquage lu d'un message direct (#373). Seul le
 * destinataire du message peut le marquer lu.
 */
final class DirectMessageReadWebControllerTest extends WebTestCase
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

    private function createMessage(User $sender, User $recipient): DirectMessage
    {
        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps');
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    public function testRejectsAnonymousUser(): void
    {
        $sender = $this->createWebUser('admin@example.com', 'secret123', 'Admin');
        $recipient = $this->createWebUser('user@example.com', 'secret123', 'User');
        $message = $this->createMessage($sender, $recipient);

        $this->client->request('POST', '/direct-messages/' . $message->getId() . '/read');

        $this->assertResponseRedirects('/login');
    }

    public function testRecipientCanMarkOwnMessageAsRead(): void
    {
        $sender = $this->createWebUser('admin@example.com', 'secret123', 'Admin');
        $recipient = $this->createWebUser('user@example.com', 'secret123', 'User');
        $message = $this->createMessage($sender, $recipient);
        $this->loginAs('user@example.com');

        $this->client->request('POST', '/direct-messages/' . $message->getId() . '/read');

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(DirectMessage::class)->find($message->getId());
        $this->assertTrue($reloaded->isRead());
    }

    public function testOtherUserCannotMarkSomeoneElsesMessageAsRead(): void
    {
        $sender = $this->createWebUser('admin@example.com', 'secret123', 'Admin');
        $recipient = $this->createWebUser('user@example.com', 'secret123', 'User');
        $other = $this->createWebUser('other@example.com', 'secret123', 'Other');
        $message = $this->createMessage($sender, $recipient);
        $this->loginAs('other@example.com');

        $this->client->request('POST', '/direct-messages/' . $message->getId() . '/read');

        $this->assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(DirectMessage::class)->find($message->getId());
        $this->assertFalse($reloaded->isRead());
    }
}
