<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\GuestAccountCreator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class GuestAccountCreatorTest extends TestCase
{
    private function makeCreator(
        ?EntityManagerInterface $em = null,
        ?ResetPasswordHelperInterface $resetPasswordHelper = null,
        ?MailerInterface $mailer = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): GuestAccountCreator {
        $em ??= $this->createMock(EntityManagerInterface::class);
        $resetPasswordHelper ??= $this->makeResetPasswordHelperStub();
        $mailer ??= $this->createMock(MailerInterface::class);
        $urlGenerator ??= $this->makeUrlGeneratorStub();

        return new GuestAccountCreator($em, $resetPasswordHelper, $mailer, $urlGenerator);
    }

    private function makeResetPasswordHelperStub(): ResetPasswordHelperInterface
    {
        $stub = $this->createMock(ResetPasswordHelperInterface::class);
        $stub->method('generateResetToken')
            ->willReturn(new ResetPasswordToken('fake-token', new \DateTimeImmutable('+1 hour'), time()));

        return $stub;
    }

    private function makeUrlGeneratorStub(): UrlGeneratorInterface
    {
        $stub = $this->createMock(UrlGeneratorInterface::class);
        $stub->method('generate')->willReturn('https://example.test/reset-password/fake-token');

        return $stub;
    }

    public function testCreatesUserWithEmptyPasswordAndDisplayNameDerivedFromEmail(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $em->expects($this->once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $creator = $this->makeCreator(em: $em, mailer: $mailer);

        $user = $creator->create('nouvel-invite@example.com');

        $this->assertSame('nouvel-invite@example.com', $user->getEmail());
        $this->assertSame('', $user->getPassword());
        $this->assertSame('Nouvel-invite', $user->getDisplayName());
        $this->assertTrue($user->isGuest(), 'Le compte créé par GuestAccountCreator doit être marqué invité (statut permanent)');
    }

    public function testSendsAnEmailToTheGuestWithTheActivationLink(): void
    {
        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(
            function ($message) use (&$sentEmail) {
                $sentEmail = $message;
            }
        );

        $creator = $this->makeCreator(mailer: $mailer);

        $creator->create('nouvel-invite@example.com');

        $this->assertNotNull($sentEmail);
        $this->assertSame('nouvel-invite@example.com', $sentEmail->getTo()[0]->getAddress());
        $this->assertSame(
            'https://example.test/reset-password/fake-token',
            $sentEmail->getContext()['activationUrl'],
        );
    }

    public function testActivationEmailContainsOwnerDisplayNameWhenProvided(): void
    {
        // "Quelqu'un vient de partager..." était trop impersonnel — afficher
        // le nom du propriétaire à l'origine de l'invitation rassure l'invité.
        $owner = new User('owner@example.com', 'Marie Dupont');

        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(
            function ($message) use (&$sentEmail) {
                $sentEmail = $message;
            }
        );

        $creator = $this->makeCreator(mailer: $mailer);
        $creator->create('nouvel-invite@example.com', $owner);

        $this->assertSame('Marie Dupont', $sentEmail->getContext()['ownerName']);
    }
}
