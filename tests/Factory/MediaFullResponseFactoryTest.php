<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Factory\MediaFullResponseFactory;
use App\Service\RawPreviewCache;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\Exception\PreviewNotFoundException;
use RonanLenouvel\RawPreviewExtractor\ExtractedPreview;
use RonanLenouvel\RawPreviewExtractor\Format\Format;
use RonanLenouvel\RawPreviewExtractor\Orientation;
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

    private function makeJpeg(int $width = 60, int $height = 40): string
    {
        $image = imagecreatetruecolor($width, $height);
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

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Le corps doit être la preview JPEG, pas les 52 Mo du RAW.
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame($previewData, $response->getContent());
    }

    public function testRedressesPreviewServedForRotatedRaw(): void
    {
        // Bug constaté en lightbox : la photo s'affichait couchée sur le côté.
        // Une preview est stockée telle que le capteur l'a vue — l'appareil se
        // contente d'enregistrer la rotation à appliquer, il ne l'applique pas.
        $rawPath = $this->tmpDir . '/portrait.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            // Paysage 90x60 à l'horizontale, mais pris en portrait.
            new ExtractedPreview($this->makeJpeg(90, 60), 90, 60, Format::NEF, Orientation::Rotate90),
        );

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        $size = getimagesizefromstring((string) $response->getContent());
        $this->assertNotFalse($size);

        // Redressée, elle doit ressortir en portrait : les dimensions
        // s'échangent. Une assertion dimensionnelle plutôt que visuelle, et un
        // imagerotate() du mauvais signe (180°) ne la trompe pas non plus.
        $this->assertSame(60, $size[0], 'La largeur doit devenir celle de la hauteur d\'origine');
        $this->assertSame(90, $size[1], 'La hauteur doit devenir celle de la largeur d\'origine');
    }

    public function testServesUprightPreviewUntouched(): void
    {
        // Cas courant : rien à faire, et surtout pas de réencodage JPEG inutile
        // qui dégraderait l'image pour rien.
        $rawPath = $this->tmpDir . '/paysage.nef';
        file_put_contents($rawPath, 'raw-bytes');
        $previewData = $this->makeJpeg(90, 60);

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($previewData, 90, 60, Format::NEF, Orientation::Normal),
        );

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        $this->assertSame($previewData, $response->getContent(), 'Une preview droite doit être servie telle quelle');
    }

    public function testDownscalesOversizedPreview(): void
    {
        // Une preview RAW fait typiquement 8256px de haut, là où un écran QHD en
        // affiche 1440 : le navigateur la réduisait déjà. On la sert donc à une
        // taille encore confortable pour le zoom, mais sans les 6 Mo inutiles.
        $rawPath = $this->tmpDir . '/grande.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($this->makeJpeg(6000, 4000), 6000, 4000, Format::NEF),
        );

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        $size = getimagesizefromstring((string) $response->getContent());
        $this->assertNotFalse($size);
        $this->assertSame(2160, $size[1], 'La hauteur doit être ramenée à la borne');
        $this->assertSame(3240, $size[0], 'Le ratio doit être conservé');
    }

    public function testLeavesSmallPreviewUntouched(): void
    {
        // Agrandir une petite preview ne créerait que du flou.
        $rawPath = $this->tmpDir . '/petite.nef';
        file_put_contents($rawPath, 'raw-bytes');
        $previewData = $this->makeJpeg(1200, 800);

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($previewData, 1200, 800, Format::NEF),
        );

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        $this->assertSame($previewData, $response->getContent());
    }

    public function testServesFileAsIsForRegularImage(): void
    {
        $jpegPath = $this->tmpDir . '/photo.jpg';
        file_put_contents($jpegPath, $this->makeJpeg());

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(false);
        $extractor->expects($this->never())->method('extract');

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
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

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Dégradation gracieuse : on rend le fichier d'origine plutôt que
        // d'échouer. Le navigateur ne l'affichera pas, mais rien ne casse.
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testCachesPreparedPreviewOnDisk(): void
    {
        // Préparer une preview coûte ~1 s : sans cache, un diaporama repaierait
        // ce prix à chaque photo et à chaque passage.
        $rawPath = $this->tmpDir . '/photo.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        // Le cœur du test : le second appel ne doit plus toucher à l'extracteur.
        $extractor->expects($this->once())->method('extract')->willReturn(
            new ExtractedPreview($this->makeJpeg(90, 60), 90, 60, Format::NEF, Orientation::Rotate90),
        );

        $cache = new RawPreviewCache($this->tmpDir);
        $factory = new MediaFullResponseFactory($extractor, $cache);

        $first = $factory->create($rawPath, 'application/octet-stream', 'photo.nef');
        $second = $factory->create($rawPath, 'application/octet-stream', 'photo.nef');

        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function testWorksWithoutCacheKey(): void
    {
        // Sans chemin relatif (appelant qui ne le connaît pas), on génère à la
        // volée plutôt que d'échouer.
        $rawPath = $this->tmpDir . '/photo.nef';
        file_put_contents($rawPath, 'raw-bytes');

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($this->makeJpeg(90, 60), 90, 60, Format::NEF),
        );

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
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

        $factory = new MediaFullResponseFactory($extractor, new RawPreviewCache($this->tmpDir));
        $response = $factory->create($rawPath, 'application/octet-stream');

        // Sans cache navigateur, un diaporama en boucle re-extrairait la preview
        // à chaque passage. Privé : le média n'appartient qu'à cet utilisateur.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=', $cacheControl);
    }
}
