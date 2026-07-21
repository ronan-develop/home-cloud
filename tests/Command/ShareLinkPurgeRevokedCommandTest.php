<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ShareLinkPurgeRevokedCommand;
use App\Entity\ShareLink;
use App\Interface\ShareLinkRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Purge des ShareLink révoqués depuis plus de ShareLink::PURGE_AFTER_DAYS
 * jours (cf. #244) : garde une fenêtre de grâce permettant la réactivation
 * (cf. app_share_link_reactivate) avant suppression physique définitive.
 */
final class ShareLinkPurgeRevokedCommandTest extends TestCase
{
    public function testPurgesRevokedLinksOlderThanRetentionWindow(): void
    {
        $repository = $this->createMock(ShareLinkRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('deleteRevokedOlderThan')
            ->with($this->callback(function (\DateTimeImmutable $threshold) {
                $expected = (new \DateTimeImmutable())->modify('-' . ShareLink::PURGE_AFTER_DAYS . ' days');

                return abs($expected->getTimestamp() - $threshold->getTimestamp()) < 5;
            }))
            ->willReturn(3);

        $tester = $this->commandTester($repository);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('3', $tester->getDisplay());
    }

    private function commandTester(ShareLinkRepositoryInterface $repository): CommandTester
    {
        $command = new ShareLinkPurgeRevokedCommand($repository);
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:share-link:purge-revoked'));
    }
}
