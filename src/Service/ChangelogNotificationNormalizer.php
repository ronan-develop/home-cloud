<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationItem;
use App\Entity\User;
use App\Interface\ChangelogFetcherInterface;
use App\Interface\NotificationNormalizerInterface;

/**
 * Normalise les entrées changelog (éphémères, API GitHub) en NotificationItem
 * (#373). Comparaison sur `mergedAt` (timestamp complet), pas `date` (tronqué
 * au jour, réservé à l'affichage) — une entrée mergée le même jour mais après
 * la dernière visite doit rester non lue (#414). Contrairement à DirectMessage
 * qui a un readAt par entité — seul service à connaître le format des entrées
 * changelog.
 *
 * Jamais visité (lastChangelogViewedAt === null) => tout marqué lu, pas
 * l'inverse : évite un faux badge géant pour tout utilisateur existant
 * dès le déploiement d'une nouvelle entrée changelog (#293).
 */
final readonly class ChangelogNotificationNormalizer implements NotificationNormalizerInterface
{
    public function __construct(
        private ChangelogFetcherInterface $changelogFetcher,
    ) {}

    public function normalize(User $user): array
    {
        $lastViewedAt = $user->getLastChangelogViewedAt();

        $items = [];

        foreach ($this->changelogFetcher->fetchEntries() as $entry) {
            $items[] = new NotificationItem(
                NotificationItem::TYPE_CHANGELOG,
                $entry['title'],
                new \DateTimeImmutable($entry['date']),
                $entry['url'],
                $lastViewedAt === null || new \DateTimeImmutable($entry['mergedAt']) <= $lastViewedAt,
            );
        }

        return $items;
    }
}
