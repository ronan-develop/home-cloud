<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\NotificationNormalizerInterface;
use App\Repository\DirectMessageRepository;

/**
 * Normalise les DirectMessage d'un utilisateur en NotificationItem (#373).
 * Seul service à connaître l'entité DirectMessage — SRP.
 */
final readonly class DirectMessageNotificationNormalizer implements NotificationNormalizerInterface
{
    public function __construct(
        private DirectMessageRepository $directMessageRepository,
    ) {}

    public function normalize(User $user): array
    {
        $items = [];

        foreach ($this->directMessageRepository->findForUser($user) as $message) {
            $items[] = new NotificationItem(
                NotificationItem::TYPE_DIRECT_MESSAGE,
                $message->getSubject(),
                $message->getCreatedAt(),
                '/direct-messages/' . $message->getId(),
                $message->isRead(),
                $message->getId()->toString(),
            );
        }

        return $items;
    }
}
