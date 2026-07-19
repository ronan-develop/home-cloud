<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UploadBatch;
use App\Interface\BatchCompletionNotifierInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prévient le propriétaire d'un lot lourd (deferred) que son traitement média
 * est terminé, par email — seul canal garanti quand l'utilisateur a quitté
 * l'écran (le worker traite hors session HTTP).
 *
 * Pose notifiedAt : conjugué au garde du handler, ce marqueur assure qu'un lot
 * n'est notifié qu'une fois même si le worker rejoue un message.
 *
 * Calqué sur ShareNotificationMailer (from no-reply, branding centralisé).
 */
final readonly class BatchCompletionNotifier implements BatchCompletionNotifierInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function notify(UploadBatch $batch): void
    {
        $galleryUrl = $this->urlGenerator->generate(
            'app_gallery',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $count = $batch->getExpectedCount();

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@homecloud.fr'))
            ->to($batch->getOwner()->getEmail())
            ->subject(sprintf(
                '%d %s prêt%s dans votre galerie',
                $count,
                $count > 1 ? 'fichiers' : 'fichier',
                $count > 1 ? 's' : '',
            ))
            ->htmlTemplate('emails/batch_ready.html.twig')
            ->context([
                'count'       => $count,
                'galleryUrl'  => $galleryUrl,
                'ownerName'   => $batch->getOwner()->getDisplayName(),
                'accentColor' => EmailBranding::ACCENT_COLOR,
            ]);

        $this->mailer->send($email);

        $batch->setNotifiedAt(new \DateTimeImmutable());
    }
}
