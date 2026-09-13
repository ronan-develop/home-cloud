<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\EmailBranding;
use App\Service\PasswordResetInitiator;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class PasswordResetInitiatorTest extends TestCase
{
    public function testSendsEmailWithResetUrl(): void
    {
        $user = new User('alice@example.com', 'Alice');

        $resetToken = new ResetPasswordToken('abc123', new \DateTimeImmutable('+1 hour'));

        $helper = $this->createMock(ResetPasswordHelperInterface::class);
        $helper->expects($this->once())
            ->method('generateResetToken')
            ->with($user)
            ->willReturn($resetToken);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email): bool {
                return $email->getTo()[0]->getAddress() === 'alice@example.com'
                    && str_starts_with($email->getFrom()[0]->getAddress(), 'no-reply@');
            }));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with('web_reset_password_confirm', ['token' => 'abc123'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://alice.example.com/reset-password/abc123');

        $request = Request::create('https://alice.example.com/');

        $service = new PasswordResetInitiator($helper, $mailer, $urlGenerator);
        $service->sendResetLink($user, $request);
    }

    public function testContextIncludesAccentColorForEmailLayout(): void
    {
        // #408 : reset_request_email.html.twig migre vers le layout email
        // partagé, qui a besoin d'accentColor comme tous les autres
        // templates (auparavant absent — ce template extends 'base.html.twig'
        // au lieu d'un layout email, donc jamais eu besoin de cette variable).
        $user = new User('alice@example.com', 'Alice');
        $resetToken = new ResetPasswordToken('abc123', new \DateTimeImmutable('+1 hour'));

        $helper = $this->createStub(ResetPasswordHelperInterface::class);
        $helper->method('generateResetToken')->willReturn($resetToken);

        $context = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$context): void {
                $context = $email->getContext();
            });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://alice.example.com/reset-password/abc123');

        $request = Request::create('https://alice.example.com/');

        $service = new PasswordResetInitiator($helper, $mailer, $urlGenerator);
        $service->sendResetLink($user, $request);

        self::assertArrayHasKey('accentColor', $context);
        self::assertSame(EmailBranding::ACCENT_COLOR, $context['accentColor']);
    }
}
