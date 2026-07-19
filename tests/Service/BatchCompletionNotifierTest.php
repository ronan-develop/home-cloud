<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\UploadBatch;
use App\Entity\User;
use App\Service\BatchCompletionNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifie le propriétaire d'un lot lourd (deferred) quand son traitement média
 * est terminé — canal email, garanti même si l'utilisateur a quitté l'écran.
 */
final class BatchCompletionNotifierTest extends TestCase
{
    public function testNotifySendsEmailToBatchOwnerAndStampsNotifiedAt(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $batch = new UploadBatch($owner, 3, 300_000_000, UploadBatch::MODE_DEFERRED);

        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (TemplatedEmail $email) use (&$sent): void {
                $sent = $email;
            });

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://home.example/gallery');

        $notifier = new BatchCompletionNotifier($mailer, $urlGenerator);
        $notifier->notify($batch);

        $this->assertNotNull($sent, 'Un email doit être envoyé');
        $this->assertSame('emails/batch_ready.html.twig', $sent->getHtmlTemplate());
        $addresses = array_map(static fn ($a) => $a->getAddress(), $sent->getTo());
        $this->assertContains('owner@example.com', $addresses);
        $this->assertNotNull($batch->getNotifiedAt(), 'notifiedAt doit être posé après envoi');
    }
}
