<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Service\ShareNotificationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * TDD RED → GREEN : notifie un invité déjà actif d'un nouveau partage
 * (nom de la ressource + URL d'accès), là où seul le tout premier partage
 * (création de compte) envoyait un email jusqu'ici.
 */
final class ShareNotificationMailerTest extends TestCase
{
    private function makeMailer(
        ?MailerInterface $mailer = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): ShareNotificationMailer {
        $mailer ??= $this->createMock(MailerInterface::class);
        $urlGenerator ??= $this->makeUrlGeneratorStub();

        return new ShareNotificationMailer($mailer, $urlGenerator);
    }

    private function makeUrlGeneratorStub(): UrlGeneratorInterface
    {
        $stub = $this->createStub(UrlGeneratorInterface::class);
        $stub->method('generate')->willReturn('https://example.test/explorer?folder=some-id');

        return $stub;
    }

    public function testSendsEmailToGuestWithResourceNameAndAccessUrl(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $guest = new User('guest@example.com', 'Guest');
        $folder = new Folder('Vacances', $owner);
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);

        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(
            function ($message) use (&$sentEmail) {
                $sentEmail = $message;
            }
        );

        $notifier = $this->makeMailer(mailer: $mailer);
        $notifier->notify($share, 'Vacances');

        $this->assertNotNull($sentEmail);
        $this->assertSame('guest@example.com', $sentEmail->getTo()[0]->getAddress());
        $this->assertSame('Vacances', $sentEmail->getContext()['resourceName']);
        $this->assertSame('https://example.test/explorer?folder=some-id', $sentEmail->getContext()['accessUrl']);
    }

    public function testAlbumResourceUsesAlbumDetailRoute(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $guest = new User('guest@example.com', 'Guest');
        $albumId = \Symfony\Component\Uid\Uuid::v7();
        $share = new Share($owner, $guest, Share::RESOURCE_ALBUM, $albumId, Share::PERMISSION_READ);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_album_detail', ['id' => $albumId->toRfc4122()], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/albums/' . $albumId->toRfc4122());

        $notifier = $this->makeMailer(urlGenerator: $urlGenerator);
        $notifier->notify($share, 'Mon Album');
    }
}
