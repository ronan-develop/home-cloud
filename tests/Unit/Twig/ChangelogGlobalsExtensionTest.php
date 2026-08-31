<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\User;
use App\Interface\ChangelogFetcherInterface;
use App\Twig\ChangelogGlobalsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class ChangelogGlobalsExtensionTest extends TestCase
{
    private function fetcherWithEntries(array $dates): ChangelogFetcherInterface
    {
        return new class($dates) implements ChangelogFetcherInterface {
            public function __construct(private readonly array $dates) {}

            public function fetchEntries(): array
            {
                $entries = [];
                foreach ($this->dates as $i => $date) {
                    $entries[] = ['number' => $i, 'title' => "Entrée {$i}", 'date' => $date, 'url' => 'https://example.test'];
                }

                return $entries;
            }
        };
    }

    public function testReturnsZeroForAnonymousUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = new ChangelogGlobalsExtension($this->fetcherWithEntries(['2026-08-01']), $security);

        $this->assertSame(['changelogUnreadCount' => 0], $extension->getGlobals());
    }

    public function testReturnsZeroWhenNeverVisited(): void
    {
        $user = new User('someone@example.com', 'Someone');
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new ChangelogGlobalsExtension($this->fetcherWithEntries(['2026-08-01', '2026-07-01']), $security);

        $this->assertSame(['changelogUnreadCount' => 0], $extension->getGlobals());
    }

    public function testCountsEntriesNewerThanLastVisit(): void
    {
        $user = new User('someone@example.com', 'Someone');
        $user->setLastChangelogViewedAt(new \DateTimeImmutable('2026-07-15'));
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new ChangelogGlobalsExtension(
            $this->fetcherWithEntries(['2026-08-01', '2026-07-20', '2026-07-10']),
            $security
        );

        $this->assertSame(['changelogUnreadCount' => 2], $extension->getGlobals());
    }

    public function testReturnsZeroWhenNoNewEntriesSinceLastVisit(): void
    {
        $user = new User('someone@example.com', 'Someone');
        $user->setLastChangelogViewedAt(new \DateTimeImmutable('2026-08-01'));
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new ChangelogGlobalsExtension(
            $this->fetcherWithEntries(['2026-07-20', '2026-07-10']),
            $security
        );

        $this->assertSame(['changelogUnreadCount' => 0], $extension->getGlobals());
    }
}
