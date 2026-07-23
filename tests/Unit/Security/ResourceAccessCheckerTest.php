<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Share;
use App\Entity\User;
use App\Interface\ShareAccessCheckerInterface;
use App\Security\ResourceAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ResourceAccessCheckerTest extends TestCase
{
    private function makeUser(): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(Uuid::v7());

        return $user;
    }

    public function testOwnerCanReadAndWrite(): void
    {
        $owner = $this->makeUser();
        $shareAccessChecker = $this->createMock(ShareAccessCheckerInterface::class);
        $shareAccessChecker->expects($this->never())->method('canAccess');

        $checker = new ResourceAccessChecker($shareAccessChecker);

        $this->assertTrue($checker->canRead($owner, Share::RESOURCE_FILE, Uuid::v7(), $owner));
        $this->assertTrue($checker->canWrite($owner, Share::RESOURCE_FILE, Uuid::v7(), $owner));
    }

    public function testGuestWithReadShareCanReadButNotWrite(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $resourceId = Uuid::v7();

        $shareAccessChecker = $this->createStub(ShareAccessCheckerInterface::class);
        $shareAccessChecker->method('canAccess')
            ->willReturnCallback(fn (User $u, string $type, Uuid $id, string $perm) => $perm === Share::PERMISSION_READ);

        $checker = new ResourceAccessChecker($shareAccessChecker);

        $this->assertTrue($checker->canRead($guest, Share::RESOURCE_FILE, $resourceId, $owner));
        $this->assertFalse($checker->canWrite($guest, Share::RESOURCE_FILE, $resourceId, $owner));
    }

    public function testGuestWithWriteShareCanReadAndWrite(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $resourceId = Uuid::v7();

        $shareAccessChecker = $this->createStub(ShareAccessCheckerInterface::class);
        $shareAccessChecker->method('canAccess')->willReturn(true);

        $checker = new ResourceAccessChecker($shareAccessChecker);

        $this->assertTrue($checker->canRead($guest, Share::RESOURCE_FILE, $resourceId, $owner));
        $this->assertTrue($checker->canWrite($guest, Share::RESOURCE_FILE, $resourceId, $owner));
    }

    public function testGuestWithoutShareCannotReadOrWrite(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $resourceId = Uuid::v7();

        $shareAccessChecker = $this->createStub(ShareAccessCheckerInterface::class);
        $shareAccessChecker->method('canAccess')->willReturn(false);

        $checker = new ResourceAccessChecker($shareAccessChecker);

        $this->assertFalse($checker->canRead($guest, Share::RESOURCE_FILE, $resourceId, $owner));
        $this->assertFalse($checker->canWrite($guest, Share::RESOURCE_FILE, $resourceId, $owner));
    }

    public function testExpiredShareDeniesReadAndWrite(): void
    {
        // ShareAccessChecker::canAccess encapsule déjà le filtre d'expiration
        // (findActiveShare) : un share expiré doit se comporter comme "aucun share".
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $resourceId = Uuid::v7();

        $shareAccessChecker = $this->createStub(ShareAccessCheckerInterface::class);
        $shareAccessChecker->method('canAccess')->willReturn(false);

        $checker = new ResourceAccessChecker($shareAccessChecker);

        $this->assertFalse($checker->canRead($guest, Share::RESOURCE_FILE, $resourceId, $owner));
        $this->assertFalse($checker->canWrite($guest, Share::RESOURCE_FILE, $resourceId, $owner));
    }
}
