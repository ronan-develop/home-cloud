<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ShareLinkPurgeRevokedCommand;
use App\Entity\ShareLink;
use App\Interface\Share\ShareLinkRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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

    // #504 : audit sécurité crons — sans try/catch, une exception (DB
    // injoignable, etc.) remonte à Symfony Console qui affiche la stack
    // trace complète dans les logs crontab (var/log/share-link-purge.log),
    // risque latent de fuite d'informations (fragments de requête SQL,
    // chemins serveur). Doit désormais être catchée, loggée proprement
    // (sans stack trace brute dans la sortie), et retourner FAILURE.
    public function testCommandCatchesExceptionAndLogsItWithoutLeakingStackTrace(): void
    {
        $repository = $this->createMock(ShareLinkRepositoryInterface::class);
        $repository->method('deleteRevokedOlderThan')
            ->willThrowException(new \RuntimeException('DATABASE_URL=mysql://user:secret@host/db injoignable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('ShareLinkPurgeRevokedCommand'), $this->arrayHasKey('exception'));

        $tester = $this->commandTester($repository, $logger);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringNotContainsString('secret', $tester->getDisplay());
    }

    private function commandTester(ShareLinkRepositoryInterface $repository, ?LoggerInterface $logger = null): CommandTester
    {
        $command = new ShareLinkPurgeRevokedCommand($repository, $logger ?? new NullLogger());
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:share-link:purge-revoked'));
    }
}
