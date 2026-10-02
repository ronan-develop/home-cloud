<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\TakeoutDispatchLog;
use App\Repository\TakeoutDispatchLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * #528 : historique des cycles de dispatch Takeout (calme / chargé / rien en
 * attente), alimenté par la commande cron toutes les 15 min — sert la page
 * admin et l'échantillonnage de charge de #547.
 */
final class TakeoutDispatchLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TakeoutDispatchLogRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(TakeoutDispatchLogRepository::class);

        $this->em->getConnection()->executeStatement('DELETE FROM takeout_dispatch_log');
        $this->em->clear();
    }

    private function insertLog(string $outcome, \DateTimeImmutable $createdAt, ?array $load = [1.0, 1.0, 1.0]): void
    {
        $log = new TakeoutDispatchLog($outcome, $load, 8.0, 2, $outcome === TakeoutDispatchLog::OUTCOME_DISPATCHED ? 2 : 0);
        $this->em->persist($log);
        $this->em->flush();

        // createdAt est fixé dans le constructeur : on force la valeur voulue en base.
        $this->em->getConnection()->executeStatement(
            'UPDATE takeout_dispatch_log SET created_at = ? WHERE id = ?',
            [$createdAt->format('Y-m-d H:i:s'), $log->getId()->toBinary()],
        );
        $this->em->clear();
    }

    public function testSavePersistsLogWithLoadAverage(): void
    {
        $log = new TakeoutDispatchLog(TakeoutDispatchLog::OUTCOME_DEFERRED, [9.5, 8.0, 7.5], 8.0, 3, 0);

        $this->repository->save($log);
        $this->em->clear();

        $found = $this->repository->find($log->getId());
        $this->assertNotNull($found);
        $this->assertSame(TakeoutDispatchLog::OUTCOME_DEFERRED, $found->getOutcome());
        $this->assertSame([9.5, 8.0, 7.5], $found->getLoadAverage());
        $this->assertSame(8.0, $found->getThreshold());
        $this->assertSame(3, $found->getScheduledCount());
        $this->assertSame(0, $found->getDispatchedCount());
    }

    public function testLoadAverageIsNullWhenMeasureUnavailable(): void
    {
        $log = new TakeoutDispatchLog(TakeoutDispatchLog::OUTCOME_DEFERRED, null, 8.0, 0, 0);

        $this->repository->save($log);
        $this->em->clear();

        $this->assertNull($this->repository->find($log->getId())->getLoadAverage());
    }

    public function testFindRecentReturnsNewestFirstAndRespectsLimit(): void
    {
        $now = new \DateTimeImmutable();
        $this->insertLog(TakeoutDispatchLog::OUTCOME_IDLE, $now->modify('-45 minutes'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-30 minutes'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DISPATCHED, $now->modify('-15 minutes'));

        $recent = $this->repository->findRecent(2);

        $this->assertCount(2, $recent);
        $this->assertSame(TakeoutDispatchLog::OUTCOME_DISPATCHED, $recent[0]->getOutcome());
        $this->assertSame(TakeoutDispatchLog::OUTCOME_DEFERRED, $recent[1]->getOutcome());
    }

    public function testFindRecentIsEmptyWhenNoLog(): void
    {
        $this->assertSame([], $this->repository->findRecent());
    }

    public function testPurgeOlderThanDeletesOnlyOldRowsAndReturnsCount(): void
    {
        $now = new \DateTimeImmutable();
        $this->insertLog(TakeoutDispatchLog::OUTCOME_IDLE, $now->modify('-31 days'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_IDLE, $now->modify('-40 days'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_IDLE, $now->modify('-2 days'));

        $deleted = $this->repository->purgeOlderThan($now->modify('-30 days'));

        $this->assertSame(2, $deleted);
        $this->assertCount(1, $this->repository->findRecent());
    }

    public function testCountByOutcomeSinceIgnoresOlderRows(): void
    {
        $now = new \DateTimeImmutable();
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DISPATCHED, $now->modify('-1 hour'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-2 hours'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-3 hours'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-3 days'));

        $counts = $this->repository->countByOutcomeSince($now->modify('-24 hours'));

        $this->assertSame(1, $counts[TakeoutDispatchLog::OUTCOME_DISPATCHED]);
        $this->assertSame(2, $counts[TakeoutDispatchLog::OUTCOME_DEFERRED]);
        $this->assertSame(0, $counts[TakeoutDispatchLog::OUTCOME_IDLE]);
    }
}
