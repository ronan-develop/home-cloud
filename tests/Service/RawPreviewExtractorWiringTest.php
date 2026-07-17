<?php

declare(strict_types=1);

namespace App\Tests\Service;

use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le package est publié en type "library" (et non "symfony-bundle") : Flex ne
 * l'enregistre donc pas automatiquement, le bundle d'intégration doit être
 * déclaré à la main dans config/bundles.php.
 */
final class RawPreviewExtractorWiringTest extends KernelTestCase
{
    public function testExtractorIsAvailableFromTheContainer(): void
    {
        self::bootKernel();

        $extractor = static::getContainer()->get(RawPreviewExtractorInterface::class);

        $this->assertInstanceOf(RawPreviewExtractorInterface::class, $extractor);
    }

    public function testExtractorRejectsNonRawFile(): void
    {
        self::bootKernel();

        /** @var RawPreviewExtractorInterface $extractor */
        $extractor = static::getContainer()->get(RawPreviewExtractorInterface::class);

        // supports() ne lève jamais : un fichier absent renvoie simplement false.
        $this->assertFalse($extractor->supports(__FILE__));
    }
}
