<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Génère un thumbnail (miniature) à partir d'un fichier image.
 *
 * Rôle : encapsuler la génération de miniature pour rendre le handler testable
 * et gérer la dégradation gracieuse quand GD n'est pas disponible.
 *
 * Choix :
 * - Utilise l'extension PHP native GD (disponible sur o2switch et la plupart des hébergements).
 * - Retourne null si GD est absent ou si la génération échoue — le Media est quand même
 *   créé, juste sans thumbnail.
 * - Thumbnail stocké dans var/storage/thumbs/{uuid}.jpg (JPEG q=80 pour équilibre taille/qualité).
 * - Taille max : 320px de large, hauteur proportionnelle.
 * - Chiffrement au repos : le fichier source est déchiffré vers un temp avant GD,
 *   le thumbnail généré est chiffré avant persistence. Le temp est supprimé dans finally.
 */
class ThumbnailService
{
    private const THUMB_WIDTH = 320;
    private const THUMB_QUALITY = 80;

    public function __construct(
        private readonly string $storageDir,
        private readonly EncryptionService $encryption,
    ) {}

    /**
     * Génère un thumbnail et retourne son chemin relatif, ou null si impossible.
     *
     * @param string $absolutePath Chemin absolu de l'image source (chiffrée sur disque)
     * @return string|null         Chemin relatif du thumbnail (ex: "thumbs/uuid.jpg") ou null
     */
    public function generate(string $absolutePath): ?string
    {
        if (!function_exists('imagecreatefromstring') || !file_exists($absolutePath)) {
            return null;
        }

        $tempPath = null;
        try {
            $tempPath = $this->encryption->decryptToTempFile($absolutePath);
            return $this->generateFromPlain($tempPath);
        } catch (\RuntimeException) {
            return null;
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    private const MAX_IMAGE_DIMENSION = 10000; // pixels — au-delà, risque GD memory bomb

    /**
     * Génère le thumbnail depuis un fichier en clair (temp), chiffre le résultat.
     */
    private function generateFromPlain(string $plainPath): ?string
    {
        // Vérifier les dimensions AVANT de charger l'image en mémoire GD
        // Une image 100000x100000 peut allouer des dizaines de Go RAM (GD bomb)
        $size = @getimagesize($plainPath);
        if ($size === false) {
            return null;
        }
        if ($size[0] > self::MAX_IMAGE_DIMENSION || $size[1] > self::MAX_IMAGE_DIMENSION) {
            return null;
        }

        $content = @file_get_contents($plainPath);
        if ($content === false) {
            return null;
        }

        $source = @imagecreatefromstring($content);
        if ($source === false) {
            return null;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $ratio = $srcH / $srcW;
        $thumbW = min(self::THUMB_WIDTH, $srcW);
        $thumbH = (int) round($thumbW * $ratio);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        if ($thumb === false) {
            imagedestroy($source);
            return null;
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);
        imagedestroy($source);

        $thumbDir = $this->storageDir.'/thumbs';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $uuid = \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
        $filename = $uuid.'.jpg';
        $fullPath = $thumbDir.'/'.$filename;

        $saved = imagejpeg($thumb, $fullPath, self::THUMB_QUALITY);
        imagedestroy($thumb);

        if (!$saved) {
            return null;
        }

        // Chiffrer le thumbnail généré — même protection que les fichiers originaux
        $this->encryption->encrypt($fullPath, $fullPath);

        return 'thumbs/'.$filename;
    }
}
