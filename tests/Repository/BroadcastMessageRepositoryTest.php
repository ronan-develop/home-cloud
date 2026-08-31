<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\BroadcastMessage;
use App\Repository\BroadcastMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BroadcastMessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BroadcastMessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(BroadcastMessageRepository::class);

        $this->em->getConnection()->executeStatement('DELETE FROM broadcast_messages');
        $this->em->clear();
    }

    public function testFindLatestReturnsNullWhenNoMessage(): void
    {
        $this->assertNull($this->repository->findLatest());
    }

    public function testFindLatestReturnsMostRecentWhenMultipleExist(): void
    {
        $older = new BroadcastMessage('Ancien message', 'Corps ancien');
        $this->em->persist($older);
        $this->em->flush();

        $this->em->getConnection()->executeStatement(
            'UPDATE broadcast_messages SET created_at = :date WHERE id = :id',
            ['date' => '2020-01-01 00:00:00', 'id' => $older->getId()->toBinary()],
        );

        $newer = new BroadcastMessage('Nouveau message', 'Corps nouveau');
        $this->em->persist($newer);
        $this->em->flush();
        $this->em->clear();

        $latest = $this->repository->findLatest();

        $this->assertNotNull($latest);
        $this->assertSame('Nouveau message', $latest->getSubject());
    }
}
