<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\GitHubChangelogFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * #290 : le changelog doit refléter automatiquement les features/bugs
 * déployés, sans maintenance manuelle — alimenté par les PR GitHub mergées,
 * filtrées par label (feature/bug/securité/performance = changements
 * utilisateur, le reste — chore/refactor/ci/docs/tests — reste interne).
 */
final class GitHubChangelogFetcherTest extends TestCase
{
    private function pr(int $number, string $title, ?string $mergedAt, array $labels): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'merged_at' => $mergedAt,
            'html_url' => "https://github.com/ronan-develop/home-cloud/pull/{$number}",
            'labels' => array_map(static fn (string $name) => ['name' => $name], $labels),
        ];
    }

    public function testKeepsOnlyMergedPullRequestsWithRelevantLabels(): void
    {
        $prs = [
            $this->pr(10, '✨ feat(Gallery): ajouter le tri par date', '2026-07-10T10:00:00Z', ['feature']),
            $this->pr(11, '🛠️ chore(deps): bump symfony/framework-bundle', '2026-07-09T10:00:00Z', ['chore']),
            $this->pr(12, '🔧 fix(Upload): corriger la barre de progression', '2026-07-08T10:00:00Z', ['bug']),
            $this->pr(13, '✨ feat(NotYetMerged): en cours', null, ['feature']),
        ];

        $client = new MockHttpClient([new MockResponse(json_encode($prs))]);
        $fetcher = new GitHubChangelogFetcher($client, new ArrayAdapter());

        $entries = $fetcher->fetchEntries();

        $this->assertCount(2, $entries);
        $this->assertSame(10, $entries[0]['number']);
        $this->assertSame(12, $entries[1]['number']);
    }

    public function testCleansEmojiAndConventionalCommitPrefixFromTitle(): void
    {
        $prs = [
            $this->pr(20, "✨ feat(FolderZipArchiver): téléchargement d'un dossier entier en zip", '2026-07-20T10:00:00Z', ['feature']),
        ];

        $client = new MockHttpClient([new MockResponse(json_encode($prs))]);
        $fetcher = new GitHubChangelogFetcher($client, new ArrayAdapter());

        $entries = $fetcher->fetchEntries();

        $this->assertSame("Téléchargement d'un dossier entier en zip", $entries[0]['title']);
    }

    public function testSortsEntriesByMergeDateDescending(): void
    {
        $prs = [
            $this->pr(30, 'feat: ancien', '2026-01-01T10:00:00Z', ['feature']),
            $this->pr(31, 'feat: récent', '2026-07-01T10:00:00Z', ['feature']),
        ];

        $client = new MockHttpClient([new MockResponse(json_encode($prs))]);
        $fetcher = new GitHubChangelogFetcher($client, new ArrayAdapter());

        $entries = $fetcher->fetchEntries();

        $this->assertSame(31, $entries[0]['number']);
        $this->assertSame(30, $entries[1]['number']);
    }

    public function testReturnsEmptyArrayWhenGitHubIsUnreachable(): void
    {
        $client = new MockHttpClient(static function (): void {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('network down');
        });
        $fetcher = new GitHubChangelogFetcher($client, new ArrayAdapter());

        $this->assertSame([], $fetcher->fetchEntries());
    }

    /**
     * L'historique complet peut dépasser les 100 PR par page de l'API GitHub
     * — il faut paginer jusqu'à épuisement pour ne pas perdre les thèmes les
     * plus anciens (#290, retour utilisateur : "tout depuis le début").
     */
    public function testPaginatesThroughAllPagesUntilExhausted(): void
    {
        $fullPage = array_map(
            fn (int $i) => $this->pr($i, "feat: theme {$i}", '2026-01-01T10:00:00Z', ['feature']),
            range(1, 100),
        );
        $lastPage = [$this->pr(101, 'feat: dernier thème', '2026-01-01T10:00:00Z', ['feature'])];

        $client = new MockHttpClient([
            new MockResponse(json_encode($fullPage)),
            new MockResponse(json_encode($lastPage)),
        ]);
        $fetcher = new GitHubChangelogFetcher($client, new ArrayAdapter());

        $entries = $fetcher->fetchEntries();

        $this->assertCount(101, $entries);
    }

    public function testResultIsCachedAndDoesNotTriggerASecondHttpCall(): void
    {
        $prs = [$this->pr(40, 'feat: une feature', '2026-07-20T10:00:00Z', ['feature'])];
        $callCount = 0;
        $client = new MockHttpClient(function () use (&$callCount, $prs): MockResponse {
            ++$callCount;

            return new MockResponse(json_encode($prs));
        });
        $cache = new ArrayAdapter();
        $fetcher = new GitHubChangelogFetcher($client, $cache);

        $fetcher->fetchEntries();
        $fetcher->fetchEntries();

        $this->assertSame(1, $callCount, 'Un second appel doit être servi depuis le cache, pas relancer une requête HTTP.');
    }
}
