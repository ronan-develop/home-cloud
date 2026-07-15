<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Crée un compte pour un email inconnu au moment d'un partage entre comptes,
 * plutôt que de faire échouer le partage avec "aucun compte associé".
 *
 * Le compte est créé sans mot de passe (User::$password garde son défaut
 * '' — password_hash() ne produit jamais '' comme hash valide, donc
 * password_verify() renvoie toujours false : le compte est structurellement
 * impossible à authentifier tant que l'invité n'a pas défini son mot de
 * passe). L'email envoyé réutilise le même mécanisme que la réinitialisation
 * de mot de passe (ResetPasswordHelperInterface, déjà dans le projet) —
 * "définir un mot de passe" et "activer un compte invité" sont la même
 * opération du point de vue de l'invité.
 */
final readonly class GuestAccountCreator
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function create(string $email, ?User $owner = null): User
    {
        $displayName = ucfirst(explode('@', $email)[0]);

        $user = new User($email, $displayName);
        $user->markAsGuest();
        $this->em->persist($user);
        $this->em->flush();

        $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        $activationUrl = $this->urlGenerator->generate(
            'web_reset_password_confirm',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $invitationEmail = (new TemplatedEmail())
            ->from(new Address('no-reply@homecloud.fr'))
            ->to($user->getEmail())
            ->subject('Vous avez été invité(e) sur HomeCloud')
            ->htmlTemplate('reset_password/guest_invitation_email.html.twig')
            ->context([
                'activationUrl' => $activationUrl,
                'ownerName'     => $owner?->getDisplayName(),
            ]);

        $this->mailer->send($invitationEmail);

        return $user;
    }
}
