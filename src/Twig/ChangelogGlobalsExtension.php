<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Interface\ChangelogFetcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `changelogUnreadCount` dans tous les templates Twig pour le badge
 * de notification de la topbar (#293).
 */
final class ChangelogGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ChangelogFetcherInterface $changelogFetcher,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['changelogUnreadCount' => 0];
        }

        $lastViewedAt = $user->getLastChangelogViewedAt();

        if ($lastViewedAt === null) {
            return ['changelogUnreadCount' => 0];
        }

        $lastViewedDate = $lastViewedAt->format('Y-m-d');
        $count = 0;
        foreach ($this->changelogFetcher->fetchEntries() as $entry) {
            if ($entry['date'] > $lastViewedDate) {
                ++$count;
            }
        }

        return ['changelogUnreadCount' => $count];
    }
}
