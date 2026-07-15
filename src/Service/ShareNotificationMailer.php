<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Share;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifie un invité déjà actif d'un nouveau partage (nom de la ressource +
 * URL d'accès). Distinct de GuestAccountCreator : celui-ci n'envoie un email
 * qu'à la création du tout premier compte (activation) — un invité qui a
 * déjà un compte actif ne recevait jusqu'ici aucune notification pour les
 * partages suivants.
 */
final readonly class ShareNotificationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function notify(Share $share, string $resourceName): void
    {
        $accessUrl = $this->resolveAccessUrl($share);

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@homecloud.fr'))
            ->to($share->getGuest()->getEmail())
            ->subject(sprintf('%s a partagé « %s » avec vous', $share->getOwner()->getDisplayName(), $resourceName))
            ->htmlTemplate('emails/share_notification.html.twig')
            ->context([
                'resourceName' => $resourceName,
                'accessUrl'    => $accessUrl,
                'ownerName'    => $share->getOwner()->getDisplayName(),
                'accentColor'  => EmailBranding::ACCENT_COLOR,
            ]);

        $this->mailer->send($email);
    }

    private function resolveAccessUrl(Share $share): string
    {
        return match ($share->getResourceType()) {
            Share::RESOURCE_ALBUM => $this->urlGenerator->generate(
                'app_album_detail',
                ['id' => $share->getResourceId()->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            Share::RESOURCE_FOLDER => $this->urlGenerator->generate(
                'app_explorer',
                ['folder' => $share->getResourceId()->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            default => $this->urlGenerator->generate('app_explorer', [], UrlGeneratorInterface::ABSOLUTE_URL),
        };
    }
}
