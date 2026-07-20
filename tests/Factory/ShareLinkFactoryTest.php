<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Exception\ResourceNotPubliclyShareableException;
use App\Factory\ShareLinkFactory;
use App\Interface\OwnershipCheckerInterface;
use App\Interface\ResourceLocatorInterface;
use App\Security\ShareLinkTokenGenerator;
use App\Security\VisibilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

final class ShareLinkFactoryTest extends TestCase
{
    private function makeFile(string $visibility, ?Folder $folder = null): File
    {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(Uuid::v7());
        $file->method('getVisibility')->willReturn($visibility);
        $file->method('getFolder')->willReturn($folder ?? $this->makeFolder(Folder::VISIBILITY_PRIVATE));

        return $file;
    }

    private function makeFolder(string $visibility, ?Folder $parent = null): Folder
    {
        $folder = $this->createMock(Folder::class);
        $folder->method('getVisibility')->willReturn($visibility);
        $folder->method('getParent')->willReturn($parent);

        return $folder;
    }

    private function makeFactory(
        File|Folder $resource,
        bool $isOwner = true,
    ): ShareLinkFactory {
        $resourceLocator = $this->createMock(ResourceLocatorInterface::class);
        $resourceLocator->method('locate')->willReturn($resource);

        $ownershipChecker = $this->createMock(OwnershipCheckerInterface::class);
        if ($isOwner) {
            $ownershipChecker->method('denyUnlessOwner');
        } else {
            $ownershipChecker->method('denyUnlessOwner')
                ->willThrowException(new AccessDeniedHttpException('not owner'));
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        return new ShareLinkFactory(
            $resourceLocator,
            $ownershipChecker,
            new VisibilityChecker(),
            new ShareLinkTokenGenerator(),
            $em,
        );
    }

    public function testCreatesLinkForPubliclyShareableResource(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId());

        $this->assertInstanceOf(ShareLink::class, $created->link);
        $this->assertNotSame('', $created->plainToken);
    }

    public function testThrowsWhenResourceIsPrivate(): void
    {
        // Le test qui compte pour cette étape : le verrou posé à l'étape 0
        // doit être appelé ici, pas seulement exister dans le vide.
        $file = $this->makeFile(File::VISIBILITY_PRIVATE);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $this->expectException(ResourceNotPubliclyShareableException::class);
        $factory->create($owner, Share::RESOURCE_FILE, $file->getId());
    }

    public function testCreatesLinkForPrivateFileWhenParentFolderIsLinkAllowed(): void
    {
        // Partage de dossier (cascade) : un fichier private hérite de
        // l'autorisation de son dossier parent.
        $linkAllowedFolder = $this->makeFolder(Folder::VISIBILITY_LINK_ALLOWED);
        $file = $this->makeFile(File::VISIBILITY_PRIVATE, $linkAllowedFolder);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId());

        $this->assertInstanceOf(ShareLink::class, $created->link);
    }

    public function testThrowsWhenNeitherResourceNorAnyAncestorIsLinkAllowed(): void
    {
        $privateFolder = $this->makeFolder(Folder::VISIBILITY_PRIVATE);
        $file = $this->makeFile(File::VISIBILITY_PRIVATE, $privateFolder);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $this->expectException(ResourceNotPubliclyShareableException::class);
        $factory->create($owner, Share::RESOURCE_FILE, $file->getId());
    }

    public function testThrowsWhenNotOwner(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file, isOwner: false);

        $this->expectException(AccessDeniedHttpException::class);
        $factory->create($owner, Share::RESOURCE_FILE, $file->getId());
    }

    public function testDefaultsExpirationToSevenDaysWhenDurationOmitted(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId());

        $expected = new \DateTimeImmutable('+7 days');
        $this->assertEqualsWithDelta($expected->getTimestamp(), $created->link->getExpiresAt()->getTimestamp(), 5);
    }

    public function testDurationOneDay(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId(), '1d');

        $expected = new \DateTimeImmutable('+1 day');
        $this->assertEqualsWithDelta($expected->getTimestamp(), $created->link->getExpiresAt()->getTimestamp(), 5);
    }

    public function testDurationThirtyDays(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId(), '30d');

        $expected = new \DateTimeImmutable('+30 days');
        $this->assertEqualsWithDelta($expected->getTimestamp(), $created->link->getExpiresAt()->getTimestamp(), 5);
    }

    public function testDurationPermanentLeavesExpiresAtNull(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId(), 'permanent');

        $this->assertNull($created->link->getExpiresAt());
    }

    public function testUnknownDurationFallsBackToSevenDays(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED);
        $owner = $this->createMock(User::class);
        $factory = $this->makeFactory($file);

        $created = $factory->create($owner, Share::RESOURCE_FILE, $file->getId(), 'not-a-real-duration');

        $expected = new \DateTimeImmutable('+7 days');
        $this->assertEqualsWithDelta($expected->getTimestamp(), $created->link->getExpiresAt()->getTimestamp(), 5);
    }
}
