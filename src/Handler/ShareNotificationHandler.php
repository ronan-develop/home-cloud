<?php

declare(strict_types=1);

namespace App\Handler;

use App\Interface\ShareNotificationMailerInterface;
use App\Interface\ShareRepositoryInterface;
use App\Message\ShareNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Handler asynchrone de la notification de partage.
 *
 * Recharge le Share par son id (il a pu être révoqué entre le dispatch et la
 * consommation → on ignore silencieusement) et délègue l'envoi au mailer, hors
 * de la requête HTTP d'origine.
 */
#[AsMessageHandler]
final class ShareNotificationHandler
{
    public function __construct(
        private readonly ShareRepositoryInterface $shareRepository,
        private readonly ShareNotificationMailerInterface $mailer,
    ) {}

    public function __invoke(ShareNotificationMessage $message): void
    {
        $share = $this->shareRepository->find(Uuid::fromString($message->shareId));
        if ($share === null) {
            return;
        }

        $this->mailer->notify($share, $message->resourceName);
    }
}
