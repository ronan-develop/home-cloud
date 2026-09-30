<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\DirectMessage;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : "Tout marquer comme lu" en un clic dans le dropdown de
 * notifications (#533) — orchestre les deux mécanismes de lecture déjà en
 * place (lastChangelogViewedAt + DirectMessage.readAt), sans nouvelle table
 * ni refonte de la pile virtuelle (#373).
 */
final class NotificationMarkAllReadWebControllerTest extends WebTestCase
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

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('POST', '/notifications/mark-all-read');

        $this->assertResponseRedirects('/login');
    }

    public function testMarksChangelogAsViewed(): void
    {
        $user = $this->createWebUser('user-changelog@example.com', 'secret123', 'User');
        $this->loginAs('user-changelog@example.com');

        $this->assertNull($user->getLastChangelogViewedAt());

        $this->client->request('POST', '/notifications/mark-all-read');

        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertNotNull($reloaded->getLastChangelogViewedAt());
    }

    public function testMarksAllDirectMessagesAsRead(): void
    {
        $sender = $this->createWebUser('sender@example.com', 'secret123', 'Sender');
        $recipient = $this->createWebUser('recipient@example.com', 'secret123', 'Recipient');
        $this->loginAs('recipient@example.com');

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $sender = $this->em->getRepository(User::class)->find($sender->getId());
        $recipient = $this->em->getRepository(User::class)->find($recipient->getId());

        $message = new DirectMessage($sender, $recipient, 'Sujet', 'Corps');
        $this->em->persist($message);
        $this->em->flush();
        $this->em->clear();

        $this->client->request('POST', '/notifications/mark-all-read');

        $this->assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(DirectMessage::class)->find($message->getId());
        $this->assertNotNull($reloaded->getReadAt());
    }

    public function testDoesNotAffectAnotherUsersMessages(): void
    {
        $sender = $this->createWebUser('sender-isolated@example.com', 'secret123', 'Sender');
        $recipient = $this->createWebUser('recipient-isolated@example.com', 'secret123', 'Recipient');
        $other = $this->createWebUser('other-isolated@example.com', 'secret123', 'Other');
        $this->loginAs('recipient-isolated@example.com');

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $sender = $this->em->getRepository(User::class)->find($sender->getId());
        $other = $this->em->getRepository(User::class)->find($other->getId());

        $theirs = new DirectMessage($sender, $other, 'Pas le mien', 'Corps');
        $this->em->persist($theirs);
        $this->em->flush();
        $this->em->clear();

        $this->client->request('POST', '/notifications/mark-all-read');

        $this->assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(DirectMessage::class)->find($theirs->getId());
        $this->assertNull($reloaded->getReadAt());
    }
}
