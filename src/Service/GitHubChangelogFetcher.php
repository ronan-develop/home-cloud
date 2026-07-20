<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ChangelogFetcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * #290 : alimente le changelog automatiquement depuis les PR GitHub mergées,
 * sans maintenance manuelle. Seules les PR labellisées comme changements
 * visibles pour l'utilisateur (feature/bug/sécurité/perf) apparaissent — le
 * reste (chore, refactor, ci, docs, tests) est du bruit pour un utilisateur
 * final.
 */
final class GitHubChangelogFetcher implements ChangelogFetcherInterface
{
    private const REPO = 'ronan-develop/home-cloud';
    private const RELEVANT_LABELS = ['feature', 'bug', 'securité', 'performance'];
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_KEY = 'changelog_github_entries';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}

    public function fetchEntries(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->fetchFromGitHub();
        });
    }

    /**
     * @return list<array{number: int, title: string, date: string, url: string}>
     */
    private function fetchFromGitHub(): array
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(
                'https://api.github.com/repos/%s/pulls?state=closed&per_page=100&sort=updated&direction=desc',
                self::REPO,
            ), [
                'headers' => ['Accept' => 'application/vnd.github+json'],
            ]);

            $pulls = $response->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($pulls)) {
            return [];
        }

        $entries = [];
        foreach ($pulls as $pull) {
            if (!is_array($pull) || empty($pull['merged_at'])) {
                continue;
            }

            $labels = array_map(
                static fn (array $label): string => strtolower((string) ($label['name'] ?? '')),
                $pull['labels'] ?? [],
            );

            if (count(array_intersect($labels, self::RELEVANT_LABELS)) === 0) {
                continue;
            }

            $entries[] = [
                'number' => (int) $pull['number'],
                'title' => $this->cleanTitle((string) $pull['title']),
                'date' => substr((string) $pull['merged_at'], 0, 10),
                'url' => (string) $pull['html_url'],
                'mergedAt' => (string) $pull['merged_at'],
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $b['mergedAt'] <=> $a['mergedAt']);

        return array_map(static function (array $entry): array {
            unset($entry['mergedAt']);

            return $entry;
        }, $entries);
    }

    /**
     * Retire l'emoji et le préfixe conventionnel ("type(scope): ") d'un titre
     * de PR pour un affichage lisible côté utilisateur final (ex: "✨
     * feat(FolderZipArchiver): télécharger un dossier en zip" → "Télécharger
     * un dossier en zip").
     */
    private function cleanTitle(string $title): string
    {
        $cleaned = preg_replace(
            '/^(\p{So}\s*)?[a-zA-Zàâäéèêëïîôöùûüç]+(\([^)]*\))?\s*:\s*/u',
            '',
            trim($title),
        ) ?? $title;

        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return trim($title);
        }

        return mb_strtoupper(mb_substr($cleaned, 0, 1)) . mb_substr($cleaned, 1);
    }
}
