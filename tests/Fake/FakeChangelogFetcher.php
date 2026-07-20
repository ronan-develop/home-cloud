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
    public function fetchEntries(): array
    {
        return [
            [
                'number' => 291,
                'title' => 'Neutraliser les PDF actifs',
                'date' => '2026-07-20',
                'url' => 'https://github.com/ronan-develop/home-cloud/pull/291',
            ],
            [
                'number' => 280,
                'title' => 'Corriger le viewer PDF',
                'date' => '2026-07-19',
                'url' => 'https://github.com/ronan-develop/home-cloud/pull/280',
            ],
        ];
    }
}
