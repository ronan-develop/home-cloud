<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationFeedProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `notificationItems` (pile unifiée) et `notificationUnreadCount`
 * dans tous les templates Twig pour le dropdown de la cloche topbar (#373).
 * Remplace ChangelogGlobalsExtension, dont la logique de comptage a migré
 * dans ChangelogNotificationNormalizer.
 */
final class NotificationGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly NotificationFeedProvider $notificationFeedProvider,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['notificationItems' => [], 'notificationUnreadCount' => 0];
        }

        $items = $this->notificationFeedProvider->getFeed($user);
        $unreadCount = count(array_filter($items, static fn ($item) => !$item->isRead));

        return ['notificationItems' => $items, 'notificationUnreadCount' => $unreadCount];
    }
}
