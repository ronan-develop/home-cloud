<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\NotificationNormalizerInterface;
use App\Service\NotificationFeedProvider;
use App\Twig\NotificationGlobalsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class NotificationGlobalsExtensionTest extends TestCase
{
    public function testExposesEmptyFeedAndZeroCountForAnonymousUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $provider = new NotificationFeedProvider([]);

        $extension = new NotificationGlobalsExtension($provider, $security);

        $globals = $extension->getGlobals();

        $this->assertSame(0, $globals['notificationUnreadCount']);
        $this->assertSame([], $globals['notificationItems']);
    }

    public function testExposesOnlyUnreadItemsForAuthenticatedUser(): void
    {
        // #411 : les items lus disparaissent du dropdown (liste plus courte,
        // ne s'encombre pas au fil du temps) — changelog reste consultable
        // sur /changelog, message direct n'a pas d'historique mais le choix
        // est assumé pour un comportement uniforme entre les deux sources.
        $user = $this->createStub(User::class);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $unread = new NotificationItem(NotificationItem::TYPE_DIRECT_MESSAGE, 'A', new \DateTimeImmutable('2026-09-01'), '/x', false);
        $read = new NotificationItem(NotificationItem::TYPE_CHANGELOG, 'B', new \DateTimeImmutable('2026-08-01'), '/y', true);

        $normalizer = $this->createStub(NotificationNormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([$unread, $read]);

        $provider = new NotificationFeedProvider([$normalizer]);

        $extension = new NotificationGlobalsExtension($provider, $security);

        $globals = $extension->getGlobals();

        $this->assertSame(1, $globals['notificationUnreadCount']);
        $this->assertSame([$unread], $globals['notificationItems']);
    }

    public function testExposesEmptyFeedWhenAllItemsAreRead(): void
    {
        $user = $this->createStub(User::class);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $read = new NotificationItem(NotificationItem::TYPE_CHANGELOG, 'B', new \DateTimeImmutable('2026-08-01'), '/y', true);

        $normalizer = $this->createStub(NotificationNormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([$read]);

        $provider = new NotificationFeedProvider([$normalizer]);

        $extension = new NotificationGlobalsExtension($provider, $security);

        $globals = $extension->getGlobals();

        $this->assertSame(0, $globals['notificationUnreadCount']);
        $this->assertSame([], $globals['notificationItems']);
    }

    public function testExposesAllItemsWhenNoneAreRead(): void
    {
        $user = $this->createStub(User::class);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $unreadA = new NotificationItem(NotificationItem::TYPE_DIRECT_MESSAGE, 'A', new \DateTimeImmutable('2026-09-01'), '/x', false);
        $unreadB = new NotificationItem(NotificationItem::TYPE_CHANGELOG, 'B', new \DateTimeImmutable('2026-08-01'), '/y', false);

        $normalizer = $this->createStub(NotificationNormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([$unreadA, $unreadB]);

        $provider = new NotificationFeedProvider([$normalizer]);

        $extension = new NotificationGlobalsExtension($provider, $security);

        $globals = $extension->getGlobals();

        $this->assertSame(2, $globals['notificationUnreadCount']);
        $this->assertSame([$unreadA, $unreadB], $globals['notificationItems']);
    }
}
