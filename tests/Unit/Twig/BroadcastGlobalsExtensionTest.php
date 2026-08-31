<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\BroadcastMessage;
use App\Entity\User;
use App\Interface\BroadcastMessageRepositoryInterface;
use App\Twig\BroadcastGlobalsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * TDD RED → GREEN : bannière in-app du broadcast admin (#361, suite de
 * #283). Expose le BroadcastMessage complet (pas juste un compteur, comme
 * ChangelogGlobalsExtension) tant que l'utilisateur ne l'a pas vu.
 */
final class BroadcastGlobalsExtensionTest extends TestCase
{
    private function repositoryWithLatest(?BroadcastMessage $message): BroadcastMessageRepositoryInterface
    {
        $stub = $this->createStub(BroadcastMessageRepositoryInterface::class);
        $stub->method('findLatest')->willReturn($message);

        return $stub;
    }

    public function testReturnsNullWhenNoUserLoggedIn(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = new BroadcastGlobalsExtension($this->repositoryWithLatest(new BroadcastMessage('S', 'B')), $security);

        $this->assertSame(['unreadBroadcastMessage' => null], $extension->getGlobals());
    }

    public function testReturnsNullWhenNoBroadcastMessageExists(): void
    {
        $user = new User('user@example.com', 'User');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new BroadcastGlobalsExtension($this->repositoryWithLatest(null), $security);

        $this->assertSame(['unreadBroadcastMessage' => null], $extension->getGlobals());
    }

    public function testReturnsMessageWhenNeverSeen(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new BroadcastGlobalsExtension($this->repositoryWithLatest($message), $security);

        $this->assertSame(['unreadBroadcastMessage' => $message], $extension->getGlobals());
    }

    public function testReturnsNullWhenAlreadySeenAfterMessageCreation(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');
        $user->setLastBroadcastSeenAt(new \DateTimeImmutable('+1 day'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new BroadcastGlobalsExtension($this->repositoryWithLatest($message), $security);

        $this->assertSame(['unreadBroadcastMessage' => null], $extension->getGlobals());
    }

    public function testReturnsMessageWhenSeenBeforeMessageWasCreated(): void
    {
        $user = new User('user@example.com', 'User');
        $user->setLastBroadcastSeenAt(new \DateTimeImmutable('-1 day'));
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $extension = new BroadcastGlobalsExtension($this->repositoryWithLatest($message), $security);

        $this->assertSame(['unreadBroadcastMessage' => $message], $extension->getGlobals());
    }
}
