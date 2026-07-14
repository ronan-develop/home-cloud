<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Album;
use App\Entity\User;
use App\Interface\ResourceAccessCheckerInterface;
use App\Security\AlbumVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

final class AlbumVoterTest extends TestCase
{
    private function makeUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v7());

        return $user;
    }

    private function makeAlbum(User $owner): Album
    {
        $album = $this->createMock(Album::class);
        $album->method('getId')->willReturn(Uuid::v7());
        $album->method('getOwner')->willReturn($owner);

        return $album;
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    public function testOwnerCanViewAlbum(): void
    {
        $owner = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('canRead')->willReturn(true);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($owner), $album, [AlbumVoter::VIEW])
        );
    }

    public function testGuestWithActiveShareCanViewAlbum(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('canRead')->willReturn(true);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($guest), $album, [AlbumVoter::VIEW])
        );
    }

    public function testGuestWithoutShareCannotViewAlbum(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('canRead')->willReturn(false);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($guest), $album, [AlbumVoter::VIEW])
        );
    }

    public function testGuestWithExpiredShareCannotViewAlbum(): void
    {
        // ResourceAccessChecker::canRead encapsule déjà l'expiration (ShareAccessChecker) :
        // un share expiré doit se comporter exactement comme "aucun share".
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('canRead')->willReturn(false);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($guest), $album, [AlbumVoter::VIEW])
        );
    }

    public function testGuestWithWriteShareCannotDeleteAlbum(): void
    {
        // ALBUM_DELETE reste owner-only : un guest write agit dans l'album,
        // il ne le détruit pas (arbitrage §3.3 de feat-partage.md).
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('canRead')->willReturn(true);
        $resourceAccessChecker->method('canWrite')->willReturn(true);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($guest), $album, [AlbumVoter::DELETE])
        );
    }

    public function testOwnerCanDeleteAlbum(): void
    {
        $owner = $this->makeUser();
        $album = $this->makeAlbum($owner);

        $resourceAccessChecker = $this->createMock(ResourceAccessCheckerInterface::class);

        $voter = new AlbumVoter($resourceAccessChecker);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($owner), $album, [AlbumVoter::DELETE])
        );
    }
}
