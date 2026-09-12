<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationFeedProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `notificationItems` (pile unifiée, non-lus uniquement — #411) et
 * `notificationUnreadCount` dans tous les templates Twig pour le dropdown de
 * la cloche topbar (#373). Remplace ChangelogGlobalsExtension, dont la
 * logique de comptage a migré dans ChangelogNotificationNormalizer.
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

        // #411 : les items lus disparaissent du dropdown — le changelog reste
        // consultable sur /changelog, comportement uniforme avec les messages
        // directs pour ne pas encombrer la pile au fil du temps.
        $items = array_values(array_filter(
            $this->notificationFeedProvider->getFeed($user),
            static fn ($item) => !$item->isRead,
        ));

        return ['notificationItems' => $items, 'notificationUnreadCount' => count($items)];
    }
}
