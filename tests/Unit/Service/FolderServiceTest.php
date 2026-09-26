<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Enum\FolderMediaType;
use App\Interface\Auth\AuthenticationResolverInterface;
use App\Interface\Folder\DefaultFolderServiceInterface;
use App\Interface\File\FilenameValidatorInterface;
use App\Interface\Folder\FolderRepositoryInterface;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Interface\Share\SharedResourceCleanerInterface;
use App\Security\GuestRestrictionChecker;
use App\Service\Folder\FolderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Stopwatch\Stopwatch;

final class FolderServiceTest extends TestCase
{
    /** @var FolderRepositoryInterface&Stub */
    private FolderRepositoryInterface $folderRepository;

    /** @var FilenameValidatorInterface&Stub */
    private FilenameValidatorInterface $filenameValidator;

    /** @var OwnershipCheckerInterface&Stub */
    private OwnershipCheckerInterface $ownershipChecker;

    /** @var AuthenticationResolverInterface&Stub */
    private AuthenticationResolverInterface $authResolver;

    /** @var DefaultFolderServiceInterface&Stub */
    private DefaultFolderServiceInterface $defaultFolderService;

    /** @var EntityManagerInterface&Stub */
    private EntityManagerInterface $em;

    /** @var SharedResourceCleanerInterface&Stub */
    private SharedResourceCleanerInterface $sharedResourceCleaner;

    private FolderService $service;

    /** @var LoggerInterface&Stub */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local puis rebuild() (#454).
        $this->folderRepository    = $this->createStub(FolderRepositoryInterface::class);
        $this->filenameValidator   = $this->createStub(FilenameValidatorInterface::class);
        $this->ownershipChecker    = $this->createStub(OwnershipCheckerInterface::class);
        $this->authResolver        = $this->createStub(AuthenticationResolverInterface::class);
        $this->defaultFolderService = $this->createStub(DefaultFolderServiceInterface::class);
        $this->em                  = $this->createStub(EntityManagerInterface::class);
        $this->sharedResourceCleaner = $this->createStub(SharedResourceCleanerInterface::class);
        $this->logger              = $this->createStub(LoggerInterface::class);

        $this->rebuild();
    }

    private function rebuild(): void
    {
        $this->service = new FolderService(
            folderRepository:      $this->folderRepository,
            filenameValidator:     $this->filenameValidator,
            ownershipChecker:      $this->ownershipChecker,
            authResolver:          $this->authResolver,
            defaultFolderService:  $this->defaultFolderService,
            em:                    $this->em,
            logger:                $this->logger,
            stopwatch:             new Stopwatch(),
            sharedResourceCleaner: $this->sharedResourceCleaner,
            guestRestrictionChecker: new GuestRestrictionChecker(),
        );
    }

    // ─── createFolder ────────────────────────────────────────────────────────

    public function testCreateFolderValidatesFilename(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $this->filenameValidator = $this->createMock(FilenameValidatorInterface::class);
        $this->filenameValidator->expects($this->once())->method('validate')->with('mon-dossier');
        $this->folderRepository->method('findOneBy')->willReturn(null);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        $this->service->createFolder($owner, 'mon-dossier', null, FolderMediaType::General);
    }

    public function testCreateFolderThrowsIfDuplicate(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $parent = new Folder('parent', $owner);
        $this->folderRepository->method('findOneBy')->willReturn(new Folder('mon-dossier', $owner, $parent));

        $this->expectException(BadRequestHttpException::class);
        $this->service->createFolder($owner, 'mon-dossier', $parent, FolderMediaType::General);
    }

    public function testCreateFolderThrowsForGuestAccount(): void
    {
        $guest = new User('guest@example.com', 'Guest');
        $guest->markAsGuest();

        $this->expectException(\App\Exception\GuestNotAllowedException::class);
        $this->service->createFolder($guest, 'mon-dossier', null, FolderMediaType::General);
    }

    public function testCreateFolderPersistsAndReturnsFolder(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $this->folderRepository->method('findOneBy')->willReturn(null);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Folder::class));
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        $folder = $this->service->createFolder($owner, 'test', null, FolderMediaType::General);
        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertSame('test', $folder->getName());
    }

    // ─── updateFolder ────────────────────────────────────────────────────────

    public function testUpdateFolderChecksOwnership(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('old-name', $owner);
        $this->ownershipChecker = $this->createMock(OwnershipCheckerInterface::class);
        $this->ownershipChecker->expects($this->once())->method('denyUnlessOwner')->with($folder);
        $this->rebuild();

        $this->service->updateFolder($folder, '', null, false, null);
    }

    public function testUpdateFolderRenamesWhenNameProvided(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('old-name', $owner);
        $this->filenameValidator = $this->createMock(FilenameValidatorInterface::class);
        $this->filenameValidator->expects($this->once())->method('validate')->with('new-name');
        $this->folderRepository->method('findOneBy')->willReturn(null);
        $this->rebuild();

        $this->service->updateFolder($folder, 'new-name', null, false, null);
        $this->assertSame('new-name', $folder->getName());
    }

    public function testUpdateFolderSkipsRenameWhenEmptyName(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('old-name', $owner);
        $this->filenameValidator = $this->createMock(FilenameValidatorInterface::class);
        $this->filenameValidator->expects($this->never())->method('validate');
        $this->rebuild();

        $this->service->updateFolder($folder, '', null, false, null);
        $this->assertSame('old-name', $folder->getName());
    }

    public function testUpdateFolderChangesMediaType(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('folder', $owner);
        $this->em->method('flush');

        $this->service->updateFolder($folder, '', FolderMediaType::Photo, false, null);
        $this->assertSame(FolderMediaType::Photo, $folder->getMediaType());
    }

    public function testUpdateFolderThrowsIfSelfParent(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('my-folder', $owner);

        $this->expectException(BadRequestHttpException::class);
        $this->service->updateFolder($folder, '', null, true, $folder);
    }

    public function testUpdateFolderThrowsOnCycleDetected(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('A', $owner);
        $child  = new Folder('B', $owner, $folder);
        $this->ownershipChecker->method('isOwner')->willReturn(true);
        // Simulate: folder appears in child's ancestor chain → cycle
        $this->folderRepository->method('findAncestorIds')
            ->willReturn([$folder->getId()->toRfc4122()]);

        $this->expectException(BadRequestHttpException::class);
        $this->service->updateFolder($folder, '', null, true, $child);
    }

    public function testUpdateFolderMovesToRoot(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $parent = new Folder('parent', $owner);
        $folder = new Folder('child', $owner, $parent);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        $this->service->updateFolder($folder, '', null, true, null);
        $this->assertNull($folder->getParent());
    }

    public function testUpdateFolderFlushesOnSuccess(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('folder', $owner);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        $this->service->updateFolder($folder, '', null, false, null);
    }

    // ─── deleteFolder ────────────────────────────────────────────────────────

    public function testDeleteFolderChecksOwnership(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('to-delete', $owner);
        $this->ownershipChecker = $this->createMock(OwnershipCheckerInterface::class);
        $this->ownershipChecker->expects($this->once())->method('denyUnlessOwner')->with($folder);
        $this->rebuild();

        $this->service->deleteFolder($folder, true);
    }

    public function testDeleteFolderWithContentsRemovesDirectly(): void
    {
        $owner  = new User('owner@example.com', 'Owner');
        $folder = new Folder('to-delete', $owner);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('remove')->with($folder);
        $this->em->expects($this->atLeast(1))->method('flush');
        $this->rebuild();

        $this->service->deleteFolder($folder, true);
    }

    public function testDeleteFolderWithoutContentsMovesFilesFirst(): void
    {
        $owner   = new User('owner@example.com', 'Owner');
        $folder  = new Folder('to-delete', $owner);
        $uploads = new Folder('Uploads', $owner);
        $file    = new File('doc.txt', 'text/plain', 100, 'path/doc.txt', $folder, $owner);
        $folder->getFiles()->add($file);

        $this->authResolver->method('getAuthenticatedUser')->willReturn($owner);
        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())->method('resolve')->willReturn($uploads);
        $this->folderRepository->method('findDescendantIds')->willReturn([]);
        $this->folderRepository->method('find')->willReturn(null);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->atLeast(2))->method('flush');
        $this->rebuild();

        $this->service->deleteFolder($folder, false);

        $this->assertSame($uploads, $file->getFolder());
    }
}
