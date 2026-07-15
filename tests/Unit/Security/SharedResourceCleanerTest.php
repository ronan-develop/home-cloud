<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Share;
use App\Interface\ShareLinkRepositoryInterface;
use App\Interface\ShareRepositoryInterface;
use App\Security\SharedResourceCleaner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SharedResourceCleanerTest extends TestCase
{
    public function testDeleteByResourceCleansBothSharesAndShareLinks(): void
    {
        $resourceId = Uuid::v7();

        $shareRepository = $this->createMock(ShareRepositoryInterface::class);
        $shareRepository->expects($this->once())
            ->method('deleteByResource')
            ->with(Share::RESOURCE_FILE, $resourceId);

        $shareLinkRepository = $this->createMock(ShareLinkRepositoryInterface::class);
        $shareLinkRepository->expects($this->once())
            ->method('deleteByResource')
            ->with(Share::RESOURCE_FILE, $resourceId);

        $cleaner = new SharedResourceCleaner($shareRepository, $shareLinkRepository);

        $cleaner->deleteByResource(Share::RESOURCE_FILE, $resourceId);
    }
}
