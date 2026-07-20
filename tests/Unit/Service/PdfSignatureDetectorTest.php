<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PdfSignatureDetector;
use PHPUnit\Framework\TestCase;

/**
 * La norme PDF (ISO 32000-1 §7.5.2) tolère que l'en-tête `%PDF-` soit décalé
 * dans les 1024 premiers octets du fichier — les vrais lecteurs PDF (Acrobat,
 * visionneuses natives) scannent cette zone. `finfo`/libmagic, utilisé par
 * BinaryFileResponse pour définir le Content-Type, est plus strict selon
 * l'environnement et peut détecter `application/octet-stream` à tort sur un
 * PDF pourtant valide (ex: fichier téléchargé depuis un site tiers ayant
 * laissé fuiter du texte de debug avant le flux réel). Ce détecteur reproduit
 * la tolérance des vrais lecteurs, indépendamment de la version de libmagic
 * installée sur le serveur.
 */
final class PdfSignatureDetectorTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'pdf_sig_test');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
    }

    public function testDetectsHeaderAtByteZero(): void
    {
        file_put_contents($this->tmpPath, '%PDF-1.4 content');

        $this->assertTrue((new PdfSignatureDetector())->detect($this->tmpPath));
    }

    public function testDetectsHeaderShiftedWithinTolerance(): void
    {
        // En-tête décalé à ~1020 octets, toujours dans la fenêtre de tolérance de 1024.
        $prefix = str_repeat('X', 1015);
        file_put_contents($this->tmpPath, $prefix . '%PDF-1.5 content');

        $this->assertTrue((new PdfSignatureDetector())->detect($this->tmpPath));
    }

    public function testRejectsHeaderBeyondToleranceWindow(): void
    {
        $prefix = str_repeat('X', 2000);
        file_put_contents($this->tmpPath, $prefix . '%PDF-1.5 content');

        $this->assertFalse((new PdfSignatureDetector())->detect($this->tmpPath));
    }

    public function testRejectsFileWithoutPdfSignature(): void
    {
        file_put_contents($this->tmpPath, '<html><body>not a pdf</body></html>');

        $this->assertFalse((new PdfSignatureDetector())->detect($this->tmpPath));
    }
}
