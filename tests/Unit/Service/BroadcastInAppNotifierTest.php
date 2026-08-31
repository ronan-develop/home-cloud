<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\BroadcastMessage;
use App\Service\BroadcastInAppNotifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN : canal in-app du broadcast admin (#361, suite de #283).
 * Persiste un BroadcastMessage local — un seul message actif à la fois,
 * pas d'effet en dry-run.
 */
final class BroadcastInAppNotifierTest extends TestCase
{
    public function testPersistsNewBroadcastMessageOnNotify(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (BroadcastMessage $message): bool {
                return $message->getSubject() === 'Maintenance' && $message->getBody() === 'Corps du message';
            }));
        $em->expects($this->once())->method('flush');

        $notifier = new BroadcastInAppNotifier($em);
        $notifier->notify('Maintenance', 'Corps du message', false);
    }

    public function testDryRunDoesNotPersistAnything(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $notifier = new BroadcastInAppNotifier($em);
        $notifier->notify('Maintenance', 'Corps du message', true);
    }
}
