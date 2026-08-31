<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BroadcastMessage;
use App\Interface\BroadcastInAppNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Diffusion d'un message admin (#283) en notification in-app (#361) sur
 * l'instance courante — pendant complémentaire de BroadcastMailer, mais
 * persisté plutôt qu'envoyé, un seul message actif à la fois.
 */
final readonly class BroadcastInAppNotifier implements BroadcastInAppNotifierInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function notify(string $subject, string $body, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $message = new BroadcastMessage($subject, $body);
        $this->em->persist($message);
        $this->em->flush();
    }
}
