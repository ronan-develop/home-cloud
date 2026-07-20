<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Interface\ChangelogFetcherInterface;

/**
 * Double de test pour ChangelogFetcherInterface — évite tout appel réseau
 * réel vers l'API GitHub dans les tests fonctionnels (#290).
 */
final class FakeChangelogFetcher implements ChangelogFetcherInterface
{
    public function __construct(private readonly int $count = 45)
    {
    }

    public function fetchEntries(): array
    {
        $entries = [];
        for ($i = 0; $i < $this->count; ++$i) {
            $number = 300 - $i;
            $entries[] = [
                'number' => $number,
                'title' => "Thème historique numéro {$i}",
                'date' => '2026-07-' . str_pad((string) max(1, 20 - $i % 20), 2, '0', \STR_PAD_LEFT),
                'url' => "https://github.com/ronan-develop/home-cloud/pull/{$number}",
            ];
        }

        return $entries;
    }
}
