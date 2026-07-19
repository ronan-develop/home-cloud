<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\Share;
use App\Handler\ShareNotificationHandler;
use App\Interface\ShareNotificationMailerInterface;
use App\Interface\ShareRepositoryInterface;
use App\Message\ShareNotificationMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Le handler asynchrone de la notification de partage : recharge le Share par
 * son id (le message ne transporte qu'un UUID + le nom de ressource) et délègue
 * l'envoi au mailer. Sortir l'envoi de la requête HTTP (#270).
 */
final class ShareNotificationHandlerTest extends TestCase
{
    public function testSendsNotificationWhenShareExists(): void
    {
        $id = Uuid::v4();
        $share = $this->createStub(Share::class);

        $repo = $this->createStub(ShareRepositoryInterface::class);
        $repo->method('find')->willReturn($share);

        $mailer = $this->createMock(ShareNotificationMailerInterface::class);
        $mailer->expects($this->once())->method('notify')->with($share, 'Album vacances');

        $handler = new ShareNotificationHandler($repo, $mailer);
        $handler(new ShareNotificationMessage((string) $id, 'Album vacances'));
    }

    public function testDoesNothingWhenShareNotFound(): void
    {
        // Le partage a pu être révoqué entre le dispatch et la consommation.
        $repo = $this->createStub(ShareRepositoryInterface::class);
        $repo->method('find')->willReturn(null);

        $mailer = $this->createMock(ShareNotificationMailerInterface::class);
        $mailer->expects($this->never())->method('notify');

        $handler = new ShareNotificationHandler($repo, $mailer);
        $handler(new ShareNotificationMessage((string) Uuid::v4(), 'Album vacances'));
    }
}
