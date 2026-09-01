<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\NotificationNormalizerInterface;
use App\Service\NotificationFeedProvider;
use PHPUnit\Framework\TestCase;

final class NotificationFeedProviderTest extends TestCase
{
    public function testMergesAndSortsItemsFromAllNormalizersByDateDescending(): void
    {
        $user = $this->createStub(User::class);

        $older = new NotificationItem(
            NotificationItem::TYPE_CHANGELOG,
            'Ancienne entrée',
            new \DateTimeImmutable('2026-08-01'),
            'https://example.com/changelog',
            true,
        );
        $newer = new NotificationItem(
            NotificationItem::TYPE_DIRECT_MESSAGE,
            'Message récent',
            new \DateTimeImmutable('2026-09-01'),
            '/direct-messages/xxx',
            false,
        );

        $normalizerA = $this->createStub(NotificationNormalizerInterface::class);
        $normalizerA->method('normalize')->willReturn([$older]);

        $normalizerB = $this->createStub(NotificationNormalizerInterface::class);
        $normalizerB->method('normalize')->willReturn([$newer]);

        $provider = new NotificationFeedProvider([$normalizerA, $normalizerB]);

        $items = $provider->getFeed($user);

        $this->assertCount(2, $items);
        $this->assertSame($newer, $items[0]);
        $this->assertSame($older, $items[1]);
    }

    public function testReturnsEmptyArrayWhenNoNormalizersProduceItems(): void
    {
        $user = $this->createStub(User::class);

        $normalizer = $this->createStub(NotificationNormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([]);

        $provider = new NotificationFeedProvider([$normalizer]);

        $this->assertSame([], $provider->getFeed($user));
    }
}
