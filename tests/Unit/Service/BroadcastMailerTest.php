<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Interface\UserRepositoryInterface;
use App\Service\BroadcastMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Validator\Validation;

/**
 * TDD RED → GREEN : diffusion d'un message admin (#283) à tous les comptes
 * de l'instance courante, invités (guest) inclus, sans bloquer l'envoi aux
 * autres destinataires si l'un d'eux a un email invalide.
 */
final class BroadcastMailerTest extends TestCase
{
    private function makeMailer(
        MailerInterface $mailer,
        UserRepositoryInterface $userRepository,
        ?LoggerInterface $logger = null,
    ): BroadcastMailer {
        return new BroadcastMailer(
            $mailer,
            $userRepository,
            Validation::createValidator(),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function makeUserRepositoryStub(array $users): UserRepositoryInterface
    {
        $stub = $this->createStub(UserRepositoryInterface::class);
        $stub->method('findAll')->willReturn($users);

        return $stub;
    }

    public function testSendsToAllUsersWithValidEmail(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $guest = new User('guest@example.com', 'Guest');
        $guest->markAsGuest();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method('send');

        $sender = $this->makeMailer($mailer, $this->makeUserRepositoryStub([$owner, $guest]));

        $count = $sender->sendToAllUsers('Maintenance', '<p>Texte</p>', dryRun: false);

        $this->assertSame(2, $count);
    }

    public function testIncludesGuestAccounts(): void
    {
        $guest = new User('guest@example.com', 'Guest');
        $guest->markAsGuest();

        $mailer = $this->createMock(MailerInterface::class);
        $sentTo = null;
        $mailer->expects($this->once())->method('send')->willReturnCallback(
            function ($message) use (&$sentTo) {
                $sentTo = $message->getTo()[0]->getAddress();
            }
        );

        $sender = $this->makeMailer($mailer, $this->makeUserRepositoryStub([$guest]));
        $sender->sendToAllUsers('Maintenance', '<p>Texte</p>', dryRun: false);

        $this->assertSame('guest@example.com', $sentTo);
    }

    public function testDryRunSendsNoEmailButReturnsCount(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $guest = new User('guest@example.com', 'Guest');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $sender = $this->makeMailer($mailer, $this->makeUserRepositoryStub([$owner, $guest]));

        $count = $sender->sendToAllUsers('Maintenance', '<p>Texte</p>', dryRun: true);

        $this->assertSame(2, $count);
    }

    public function testSkipsInvalidEmailAndLogsWarning(): void
    {
        $valid = new User('valid@example.com', 'Valid');
        $invalid = new User('pas-un-email', 'Invalid');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $sender = $this->makeMailer($mailer, $this->makeUserRepositoryStub([$valid, $invalid]), $logger);

        $count = $sender->sendToAllUsers('Maintenance', '<p>Texte</p>', dryRun: false);

        $this->assertSame(1, $count);
    }
}
