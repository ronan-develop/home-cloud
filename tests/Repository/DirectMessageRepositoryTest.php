<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\DirectMessage;
use App\Entity\User;
use App\Repository\DirectMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * findForUser()/countUnreadForUser() comparaient `dm.recipient = :user` avec
 * un objet User passé nu en paramètre (sans type 'uuid' explicite), contrairement
 * au pattern établi ailleurs dans le projet (setParameter($id, 'uuid')) — Doctrine
 * ne résolvait pas correctement le type binaire, la requête ne matchait jamais
 * aucune ligne. Invisible jusqu'ici : le seul test existant
 * (DirectMessageNotificationNormalizerTest) mocke le repository, le vrai DQL
 * n'avait jamais tourné contre une vraie base.
 */
final class DirectMessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DirectMessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(DirectMessageRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM direct_messages');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant-hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testFindForUserReturnsEmptyArrayWhenNoMessages(): void
    {
        $recipient = $this->createUser('recipient-empty@example.com');

        $this->assertSame([], $this->repository->findForUser($recipient));
    }

    public function testFindForUserReturnsMessagesAddressedToRecipient(): void
    {
        $sender = $this->createUser('sender-find@example.com');
        $recipient = $this->createUser('recipient-find@example.com');
        $other = $this->createUser('other-find@example.com');

        $forRecipient = new DirectMessage($sender, $recipient, 'Pour le bon destinataire', 'Corps');
        $forOther = new DirectMessage($sender, $other, 'Pour un autre', 'Corps');
        $this->em->persist($forRecipient);
        $this->em->persist($forOther);
        $this->em->flush();
        $this->em->clear();

        $freshRecipient = $this->em->getRepository(User::class)->find($recipient->getId());
        $result = $this->repository->findForUser($freshRecipient);

        $this->assertCount(1, $result);
        $this->assertSame('Pour le bon destinataire', $result[0]->getSubject());
    }

    public function testFindForUserOrdersByCreatedAtDescending(): void
    {
        $sender = $this->createUser('sender-order@example.com');
        $recipient = $this->createUser('recipient-order@example.com');

        $older = new DirectMessage($sender, $recipient, 'Plus ancien', 'Corps');
        $newer = new DirectMessage($sender, $recipient, 'Plus récent', 'Corps');
        $this->em->persist($older);
        $this->em->persist($newer);
        $this->em->flush();

        $reflection = new \ReflectionProperty(DirectMessage::class, 'createdAt');
        $reflection->setValue($older, new \DateTimeImmutable('-2 days'));
        $reflection->setValue($newer, new \DateTimeImmutable('-1 day'));
        $this->em->flush();
        $this->em->clear();

        $freshRecipient = $this->em->getRepository(User::class)->find($recipient->getId());
        $result = $this->repository->findForUser($freshRecipient);

        $this->assertCount(2, $result);
        $this->assertSame('Plus récent', $result[0]->getSubject());
        $this->assertSame('Plus ancien', $result[1]->getSubject());
    }

    public function testCountUnreadForUserReturnsZeroWhenNoMessages(): void
    {
        $recipient = $this->createUser('recipient-count-empty@example.com');

        $this->assertSame(0, $this->repository->countUnreadForUser($recipient));
    }

    public function testCountUnreadForUserCountsOnlyUnreadMessages(): void
    {
        $sender = $this->createUser('sender-count@example.com');
        $recipient = $this->createUser('recipient-count@example.com');

        $unread = new DirectMessage($sender, $recipient, 'Non lu', 'Corps');
        $read = new DirectMessage($sender, $recipient, 'Lu', 'Corps');
        $read->markAsRead();

        $this->em->persist($unread);
        $this->em->persist($read);
        $this->em->flush();
        $this->em->clear();

        $freshRecipient = $this->em->getRepository(User::class)->find($recipient->getId());

        $this->assertSame(1, $this->repository->countUnreadForUser($freshRecipient));
    }
}
