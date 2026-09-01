<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\NotificationNormalizerInterface;

/**
 * Fusionne les items produits par chaque NotificationNormalizerInterface
 * (DirectMessage, changelog...) en une pile unique triée chronologiquement
 * (#373). Ne connaît que le DTO commun NotificationItem — SRP : chaque
 * normalizer reste seul responsable de sa source.
 */
final readonly class NotificationFeedProvider
{
    /**
     * @param iterable<NotificationNormalizerInterface> $normalizers
     */
    public function __construct(
        private iterable $normalizers,
    ) {}

    /**
     * @return list<NotificationItem>
     */
    public function getFeed(User $user): array
    {
        $items = [];

        foreach ($this->normalizers as $normalizer) {
            array_push($items, ...$normalizer->normalize($user));
        }

        usort($items, static fn (NotificationItem $a, NotificationItem $b) => $b->date <=> $a->date);

        return $items;
    }
}
