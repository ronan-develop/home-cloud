<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Interface\PasswordResetInitiatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Génération de token + envoi d'email de reset password, extrait du
 * contrôleur self-service (ResetPasswordController) pour être réutilisé
 * par l'action admin-initiated de #375, sans dupliquer la logique.
 */
final class PasswordResetInitiator implements PasswordResetInitiatorInterface
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function sendResetLink(User $user, Request $request): void
    {
        $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        $resetUrl = $this->urlGenerator->generate(
            'web_reset_password_confirm',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@' . $request->getHost()))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/reset_request_email.html.twig')
            ->context(['resetUrl' => $resetUrl]);

        $this->mailer->send($email);
    }
}
