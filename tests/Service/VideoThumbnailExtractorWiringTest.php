<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Interface\VideoThumbnailExtractorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VideoThumbnailExtractorWiringTest extends KernelTestCase
{
    public function testVideoThumbnailExtractorIsWiredCorrectly(): void
    {
        $this->bootKernel();
        $container = $this->getContainer();

        $extractor = $container->get(VideoThumbnailExtractorInterface::class);

        $this->assertInstanceOf(VideoThumbnailExtractorInterface::class, $extractor);
    }
}
