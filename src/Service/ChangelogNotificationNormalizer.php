<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\ChangelogFetcherInterface;
use App\Interface\NotificationNormalizerInterface;

/**
 * Normalise les entrées changelog (éphémères, API GitHub) en NotificationItem
 * (#373). Reprend la logique de lu/non-lu de ChangelogGlobalsExtension
 * (comparaison de date, contrairement à DirectMessage qui a un readAt par
 * entité) — seul service à connaître le format des entrées changelog.
 */
final readonly class ChangelogNotificationNormalizer implements NotificationNormalizerInterface
{
    public function __construct(
        private ChangelogFetcherInterface $changelogFetcher,
    ) {}

    public function normalize(User $user): array
    {
        $lastViewedAt = $user->getLastChangelogViewedAt();
        $lastViewedDate = $lastViewedAt?->format('Y-m-d');

        $items = [];

        foreach ($this->changelogFetcher->fetchEntries() as $entry) {
            $items[] = new NotificationItem(
                NotificationItem::TYPE_CHANGELOG,
                $entry['title'],
                new \DateTimeImmutable($entry['date']),
                $entry['url'],
                $lastViewedDate !== null && $entry['date'] <= $lastViewedDate,
            );
        }

        return $items;
    }
}
