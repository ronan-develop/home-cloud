<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Service\InstanceMonitoringReporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * TDD RED → GREEN : snapshot local de l'instance courante pour le monitoring
 * multi-instances (#376) — nb users, stockage total, révision git déployée.
 */
final class InstanceMonitoringReporterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InstanceMonitoringReporter $reporter;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->reporter = $container->get(InstanceMonitoringReporter::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    public function testSnapshotIsAlwaysReachable(): void
    {
        $snapshot = $this->reporter->getLocalSnapshot();

        self::assertTrue($snapshot->reachable);
    }

    public function testSnapshotReportsCorrectUserCountAndStorage(): void
    {
        $owner = new User('owner-monitoring@example.com', 'Owner');
        $owner->setPassword('irrelevant-hash');
        $this->em->persist($owner);
        $this->em->flush();

        $folder = new Folder('Uploads', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $file = new File('a.txt', 'application/octet-stream', 1234, 'irrelevant/path', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        $snapshot = $this->reporter->getLocalSnapshot();

        self::assertSame(1, $snapshot->userCount);
        self::assertSame(1234, $snapshot->totalStorageBytes);
    }

    public function testSnapshotReportsNonEmptyGitRevision(): void
    {
        $snapshot = $this->reporter->getLocalSnapshot();

        self::assertNotSame('', $snapshot->gitRevision);
    }
}
