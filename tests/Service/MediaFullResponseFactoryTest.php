<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MediaFullResponseFactory;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\Exception\PreviewNotFoundException;
use RonanLenouvel\RawPreviewExtractor\ExtractedPreview;
use RonanLenouvel\RawPreviewExtractor\Format\Format;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * L'affichage plein écran servait le fichier brut : pour un RAW, le navigateur
 * téléchargeait 52 Mo qu'il ne sait pas décoder, et n'affichait rien. On sert
 * désormais la preview JPEG embarquée — que la lightbox, le diaporama et les
 * partages publics savent afficher.
 */
final class MediaFullResponseFactoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/hc-media-full-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    private function makeJpeg(): string
    {
        $image = imagecreatetruecolor(60, 40);
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    public function testServesEmbeddedPreviewForRawFile(): void
    {
        $rawPath = $this->tmpDir . '/photo.nef';
        file_put_contents($rawPath, 'raw-bytes');
        $previewData = $this->makeJpeg();

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($previewData, 60, 40, Format::NEF),
        );

        $factory = new MediaFullResponseFactory($extractor);
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Le corps doit être la preview JPEG, pas les 52 Mo du RAW.
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame($previewData, $response->getContent());
    }

    public function testServesFileAsIsForRegularImage(): void
    {
        $jpegPath = $this->tmpDir . '/photo.jpg';
        file_put_contents($jpegPath, $this->makeJpeg());

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(false);
        $extractor->expects($this->never())->method('extract');

        $factory = new MediaFullResponseFactory($extractor);
        $response = $factory->create($jpegPath, 'image/jpeg');

        // Une image classique continue d'être streamée depuis le disque, sans
        // être chargée en mémoire.
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function testFallsBackToRawFileWhenPreviewIsMissing(): void
    {
        $rawPath = $this->tmpDir . '/photo.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willThrowException(new PreviewNotFoundException('none'));

        $factory = new MediaFullResponseFactory($extractor);
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Dégradation gracieuse : on rend le fichier d'origine plutôt que
        // d'échouer. Le navigateur ne l'affichera pas, mais rien ne casse.
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testPreviewIsCacheableByTheBrowser(): void
    {
        $rawPath = $this->tmpDir . '/photo.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($this->makeJpeg(), 60, 40, Format::NEF),
        );

        $factory = new MediaFullResponseFactory($extractor);
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Sans cache navigateur, un diaporama en boucle re-extrairait la preview
        // à chaque passage. Privé : le média n'appartient qu'à cet utilisateur.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=', $cacheControl);
    }
}
