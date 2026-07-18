<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ExifThumbnailExtractorInterface;
use RonanLenouvel\RawPreviewExtractor\Exception\RawPreviewExtractorException;
use RonanLenouvel\RawPreviewExtractor\Orientation;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

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
 * - Les fichiers source sont stockés en clair — lecture directe sans déchiffrement.
 * - Fichiers RAW (CR2/CR3/NEF/ARW/DNG) : GD ne sait pas les décoder. On extrait
 *   la preview JPEG que l'appareil y a embarquée et on l'utilise comme source du
 *   pipeline GD habituel — seule la source change, le redimensionnement est le même.
 */
class ThumbnailService
{
    private const THUMB_WIDTH = 320;
    private const THUMB_QUALITY = 80;

    public function __construct(
        private readonly string $storageDir,
        private readonly RawPreviewExtractorInterface $rawPreviewExtractor,
        private readonly ExifThumbnailExtractorInterface $exifThumbnailExtractor,
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

        try {
            if ($this->rawPreviewExtractor->supports($absolutePath)) {
                return $this->generateFromRaw($absolutePath);
            }

            return $this->generateFromPlain($absolutePath);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Génère le thumbnail depuis la preview JPEG embarquée dans un RAW.
     *
     * La preview est stockée telle que le capteur l'a vue : une photo prise en
     * portrait ressort couchée, l'appareil se contentant d'enregistrer la
     * rotation à appliquer. On la redresse ici, GD étant de toute façon chargé
     * pour le redimensionnement.
     */
    private function generateFromRaw(string $absolutePath): ?string
    {
        try {
            $preview = $this->rawPreviewExtractor->extract($absolutePath);
        } catch (RawPreviewExtractorException) {
            // RAW sans preview, illisible ou format non géré : pas de vignette,
            // le Media est créé sans, comme pour une image GD en échec.
            return null;
        }

        $source = @imagecreatefromstring($preview->jpegData);
        if ($source === false) {
            return null;
        }

        return $this->resizeAndSave($source, $preview->orientation);
    }

    private const MAX_IMAGE_DIMENSION = 10000; // pixels — au-delà, risque GD memory bomb

    /**
     * Génère le thumbnail depuis un fichier image en clair.
     *
     * La plupart des JPEG embarquent déjà une miniature dans leur IFD1 EXIF :
     * l'utiliser évite de décoder l'image pleine résolution avec GD, un
     * décodage qui peut à lui seul saturer la mémoire du worker sur un scan
     * haute résolution. Repli sur le décodage complet si elle est absente.
     */
    private function generateFromPlain(string $plainPath): ?string
    {
        $exifThumbnail = $this->exifThumbnailExtractor->extract($plainPath);
        if ($exifThumbnail !== null) {
            $source = @imagecreatefromstring($exifThumbnail->jpegData);
            if ($source !== false) {
                return $this->resizeAndSave($source, $exifThumbnail->orientation);
            }
        }

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

        return $this->resizeAndSave($source, Orientation::Normal);
    }

    /**
     * Redresse une image GD déjà chargée, la redimensionne, et l'enregistre en JPEG.
     *
     * Partagé par les deux sources possibles (fichier image en clair, ou preview
     * extraite d'un RAW) : seule la façon d'obtenir la ressource GD diffère.
     *
     * L'ordre compte : on redresse AVANT de redimensionner. Contraindre la
     * largeur à 320px sur une image encore couchée (8256×5504 en Rotate90)
     * donnerait une vignette de 213px de large une fois tournée, plus petite que
     * les photos paysage.
     *
     * @param \GdImage $source Ressource GD, détruite par cette méthode
     */
    private function resizeAndSave(\GdImage $source, Orientation $orientation): ?string
    {
        $source = $this->applyOrientation($source, $orientation);

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

        return 'thumbs/'.$filename;
    }

    /**
     * Redresse la vignette selon l'orientation enregistrée par l'appareil.
     *
     * imagerotate() tourne dans le sens antihoraire là où l'EXIF compte en
     * horaire, d'où la négation de l'angle.
     */
    private function applyOrientation(\GdImage $thumb, Orientation $orientation): \GdImage
    {
        if ($orientation->isUpright()) {
            return $thumb;
        }

        if ($orientation->isMirrored()) {
            imageflip($thumb, IMG_FLIP_HORIZONTAL);
        }

        $degrees = $orientation->degrees();
        if ($degrees === 0) {
            return $thumb;
        }

        $rotated = @imagerotate($thumb, -$degrees, 0);
        if ($rotated === false) {
            return $thumb;
        }

        imagedestroy($thumb);

        return $rotated;
    }
}
