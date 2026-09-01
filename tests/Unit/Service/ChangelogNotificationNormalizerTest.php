<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\ChangelogFetcherInterface;
use App\Service\ChangelogNotificationNormalizer;
use PHPUnit\Framework\TestCase;

final class ChangelogNotificationNormalizerTest extends TestCase
{
    public function testNormalizesChangelogEntriesAsReadWhenViewedAfterEntryDate(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getLastChangelogViewedAt')->willReturn(new \DateTimeImmutable('2026-09-01'));

        $fetcher = $this->createStub(ChangelogFetcherInterface::class);
        $fetcher->method('fetchEntries')->willReturn([
            ['number' => 373, 'title' => 'Notifications', 'date' => '2026-08-15', 'url' => 'https://github.com/x/y/pull/373'],
        ]);

        $normalizer = new ChangelogNotificationNormalizer($fetcher);

        $items = $normalizer->normalize($user);

        $this->assertCount(1, $items);
        $this->assertSame(NotificationItem::TYPE_CHANGELOG, $items[0]->type);
        $this->assertSame('Notifications', $items[0]->title);
        $this->assertSame('https://github.com/x/y/pull/373', $items[0]->link);
        $this->assertTrue($items[0]->isRead);
    }

    public function testMarksEntryAsUnreadWhenNewerThanLastViewed(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getLastChangelogViewedAt')->willReturn(new \DateTimeImmutable('2026-08-01'));

        $fetcher = $this->createStub(ChangelogFetcherInterface::class);
        $fetcher->method('fetchEntries')->willReturn([
            ['number' => 373, 'title' => 'Notifications', 'date' => '2026-08-15', 'url' => 'https://github.com/x/y/pull/373'],
        ]);

        $normalizer = new ChangelogNotificationNormalizer($fetcher);

        $items = $normalizer->normalize($user);

        $this->assertFalse($items[0]->isRead);
    }

    public function testMarksAllAsReadWhenNeverViewed(): void
    {
        // Jamais visité != tout non lu : évite un faux badge géant pour tout
        // utilisateur existant dès le déploiement d'une nouvelle entrée (#293).
        $user = $this->createStub(User::class);
        $user->method('getLastChangelogViewedAt')->willReturn(null);

        $fetcher = $this->createStub(ChangelogFetcherInterface::class);
        $fetcher->method('fetchEntries')->willReturn([
            ['number' => 373, 'title' => 'Notifications', 'date' => '2026-08-15', 'url' => 'https://github.com/x/y/pull/373'],
        ]);

        $normalizer = new ChangelogNotificationNormalizer($fetcher);

        $items = $normalizer->normalize($user);

        $this->assertTrue($items[0]->isRead);
    }
}
