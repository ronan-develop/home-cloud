<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Takeout\TakeoutZipExtractor;
use App\Exception\Takeout\ZipBombDetectedException;
use PHPUnit\Framework\TestCase;

/**
 * #327 — extraction d'un ZIP Google Takeout sur disque temporaire.
 *
 * Path traversal : protection native de ZipArchive::extractTo() (vérifié
 * empiriquement sur PHP 8.4.23, un entry "../../etc/passwd" reste confiné
 * dans le dossier cible) — pas de logique supplémentaire nécessaire ici.
 *
 * Zip bomb : PHP n'a aucune protection native, à charge de ce service via
 * les stats ZipArchive::statIndex() (taille annoncée), lues AVANT extraction.
 */
final class TakeoutZipExtractorTest extends TestCase
{
    private TakeoutZipExtractor $extractor;
    private string $workDir;

    protected function setUp(): void
    {
        $this->extractor = new TakeoutZipExtractor();
        $this->workDir = tempnam(sys_get_temp_dir(), 'takeout_work_');
        unlink($this->workDir);
        mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'takeout_zip_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    public function testExtractsNormalZipToDestination(): void
    {
        $zipPath = $this->makeZip(['photo.jpg' => 'contenu jpg']);

        $this->extractor->extract($zipPath, $this->workDir);

        $this->assertFileExists($this->workDir . '/photo.jpg');
        $this->assertSame('contenu jpg', file_get_contents($this->workDir . '/photo.jpg'));

        @unlink($zipPath);
    }

    public function testExtractsNestedStructure(): void
    {
        $zipPath = $this->makeZip([
            'Google Photos/2026/photo.jpg' => 'contenu',
        ]);

        $this->extractor->extract($zipPath, $this->workDir);

        $this->assertFileExists($this->workDir . '/Google Photos/2026/photo.jpg');

        @unlink($zipPath);
    }

    public function testRejectsZipExceedingMaxTotalUncompressedSize(): void
    {
        // Un seul entry qui annonce dépasser le plafond absolu (fixé dans le
        // service) — on ne construit pas un vrai fichier de cette taille,
        // seule la taille ANNONCÉE dans les stats ZIP compte pour la détection,
        // avant toute extraction réelle sur disque.
        $zipPath = $this->makeZip(['normal.txt' => 'contenu compact']);

        $extractor = new TakeoutZipExtractor(maxTotalUncompressedBytes: 1);

        $this->expectException(ZipBombDetectedException::class);

        $extractor->extract($zipPath, $this->workDir);

        @unlink($zipPath);
    }

    public function testRejectsEntryWithSuspiciousCompressionRatio(): void
    {
        // 1 Mo de zéros compresse à quelques octets sous DEFLATE — ratio
        // largement au-delà de ce qu'une vraie photo/vidéo produit.
        $zipPath = $this->makeZip(['suspect.bin' => str_repeat("\0", 1_000_000)]);

        $extractor = new TakeoutZipExtractor(maxCompressionRatio: 100);

        $this->expectException(ZipBombDetectedException::class);

        $extractor->extract($zipPath, $this->workDir);

        @unlink($zipPath);
    }

    public function testDoesNotExtractAnythingWhenZipBombDetected(): void
    {
        $zipPath = $this->makeZip([
            'legit.jpg' => 'photo normale',
            'bomb.bin' => str_repeat("\0", 1_000_000),
        ]);

        $extractor = new TakeoutZipExtractor(maxCompressionRatio: 100);

        try {
            $extractor->extract($zipPath, $this->workDir);
            $this->fail('ZipBombDetectedException attendue');
        } catch (ZipBombDetectedException) {
            // attendu
        }

        $this->assertFileDoesNotExist($this->workDir . '/legit.jpg', 'Aucun fichier ne doit être extrait si un zip bomb est détecté, même partiellement légitime');

        @unlink($zipPath);
    }

    public function testThrowsWhenZipFileIsInvalid(): void
    {
        $invalidPath = tempnam(sys_get_temp_dir(), 'not_a_zip_');
        file_put_contents($invalidPath, 'ceci n\'est pas un zip');

        $this->expectException(\RuntimeException::class);

        $this->extractor->extract($invalidPath, $this->workDir);

        @unlink($invalidPath);
    }

    // #525 : checkpoint d'extraction — sans lui, une coupure en plein milieu
    // (LVE du mutualisé o2switch, #520/#522) forçait à ré-extraire tout le
    // ZIP depuis 0, perte de travail potentiellement conséquente sur un ZIP
    // volumineux à 98% déjà extrait. Même pattern que ChunkedFileAssembler
    // (#466) : marqueur .progress traçant le dernier index d'entrée extrait
    // avec succès, plutôt que de déduire la position depuis l'état du disque.
    public function testResumesExtractionFromLastCheckpointedEntry(): void
    {
        $zipPath = $this->makeZip([
            'a.jpg' => 'contenu a',
            'b.jpg' => 'contenu b',
            'c.jpg' => 'contenu c',
        ]);

        // Simule une coupure après extraction de a.jpg et b.jpg (index 0 et 1),
        // avec un contenu DIFFÉRENT du ZIP (déjà modifié depuis, ex. par
        // l'utilisateur) — prouve que l'extraction reprend au lieu de
        // ré-extraire depuis 0 (sinon ce contenu serait écrasé).
        file_put_contents($this->workDir . '/a.jpg', 'déjà extrait, ne doit pas être réécrit');
        file_put_contents($this->workDir . '/b.jpg', 'déjà extrait, ne doit pas être réécrit');
        file_put_contents($this->workDir . '/.progress', '1');

        $this->extractor->extract($zipPath, $this->workDir);

        $this->assertSame('déjà extrait, ne doit pas être réécrit', file_get_contents($this->workDir . '/a.jpg'));
        $this->assertSame('déjà extrait, ne doit pas être réécrit', file_get_contents($this->workDir . '/b.jpg'));
        $this->assertFileExists($this->workDir . '/c.jpg');
        $this->assertSame('contenu c', file_get_contents($this->workDir . '/c.jpg'));

        @unlink($zipPath);
    }

    public function testRemovesProgressMarkerOnceExtractionCompletes(): void
    {
        $zipPath = $this->makeZip(['a.jpg' => 'contenu a', 'b.jpg' => 'contenu b']);

        $this->extractor->extract($zipPath, $this->workDir);

        $this->assertFileDoesNotExist($this->workDir . '/.progress');

        @unlink($zipPath);
    }

    public function testDoesNothingWhenAlreadyFullyExtracted(): void
    {
        $zipPath = $this->makeZip(['a.jpg' => 'contenu a']);

        $this->extractor->extract($zipPath, $this->workDir);
        $this->assertFileDoesNotExist($this->workDir . '/.progress');

        // Deuxième appel (retry après un succès déjà acté) : pas de marqueur
        // .progress, donc rien à reprendre — no-op silencieux, pas d'erreur.
        $this->extractor->extract($zipPath, $this->workDir);
        $this->assertSame('contenu a', file_get_contents($this->workDir . '/a.jpg'));

        @unlink($zipPath);
    }

    public function testReturnsTrueWhenFullyExtracted(): void
    {
        $zipPath = $this->makeZip(['a.jpg' => 'contenu a']);

        $isComplete = $this->extractor->extract($zipPath, $this->workDir);

        $this->assertTrue($isComplete);

        @unlink($zipPath);
    }

    // #543 (suite) : un kill LVE peut survenir à tout moment sur un ZIP
    // volumineux (constaté en conditions réelles — 1621 entrées, tué avant la
    // fin même avec le checkpoint .progress déjà en place). Limiter le nombre
    // d'entrées extraites par appel réduit le travail exposé à un kill et
    // permet au handler de redispatcher rapidement (nouveau message Messenger)
    // plutôt que d'attendre le prochain cycle nightly-dispatch (15 min).
    public function testStopsAfterMaxEntriesPerCallAndReturnsFalse(): void
    {
        $zipPath = $this->makeZip([
            'a.jpg' => 'contenu a',
            'b.jpg' => 'contenu b',
            'c.jpg' => 'contenu c',
        ]);
        $extractor = new TakeoutZipExtractor(maxEntriesPerCall: 2);

        $isComplete = $extractor->extract($zipPath, $this->workDir);

        $this->assertFalse($isComplete);
        $this->assertFileExists($this->workDir . '/a.jpg');
        $this->assertFileExists($this->workDir . '/b.jpg');
        $this->assertFileDoesNotExist($this->workDir . '/c.jpg');
        $this->assertSame('1', file_get_contents($this->workDir . '/.progress'));

        @unlink($zipPath);
    }

    public function testResumesAndCompletesAcrossMultipleBatchedCalls(): void
    {
        $zipPath = $this->makeZip([
            'a.jpg' => 'contenu a',
            'b.jpg' => 'contenu b',
            'c.jpg' => 'contenu c',
        ]);
        $extractor = new TakeoutZipExtractor(maxEntriesPerCall: 2);

        $firstCall = $extractor->extract($zipPath, $this->workDir);
        $secondCall = $extractor->extract($zipPath, $this->workDir);

        $this->assertFalse($firstCall);
        $this->assertTrue($secondCall);
        $this->assertFileExists($this->workDir . '/c.jpg');
        $this->assertFileDoesNotExist($this->workDir . '/.progress');

        @unlink($zipPath);
    }
}
