<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Extrait les métadonnées EXIF d'un fichier image.
 *
 * Rôle : isoler la lecture EXIF pour rendre MediaProcessHandler testable
 * et pour gérer proprement les cas où les EXIF sont absents ou invalides.
 *
 * Choix :
 * - Utilise l'extension PHP native `exif_read_data` (disponible sur o2switch).
 * - Retourne un tableau normalisé avec des clés fixes — le handler ne manipule
 *   jamais les données EXIF brutes.
 * - Toutes les valeurs sont nullable : une photo sans GPS ou sans date est valide.
 * - Les fichiers sont stockés en clair sur disque — lecture directe sans déchiffrement.
 */
class ExifService
{
    public function __construct(
        private readonly ExifValueFormatter $formatter = new ExifValueFormatter(),
    ) {}

    /**
     * Extrait les métadonnées EXIF d'une image.
     *
     * @param string $absolutePath Chemin absolu vers le fichier image
     * @return array{
     *     width: int|null,
     *     height: int|null,
     *     takenAt: \DateTimeImmutable|null,
     *     cameraModel: string|null,
     *     gpsLat: string|null,
     *     gpsLon: string|null,
     *     aperture: string|null,
     *     shutterSpeed: string|null,
     *     iso: int|null,
     *     focalLength: string|null,
     *     lens: string|null,
     * }
     */
    public function extract(string $absolutePath): array
    {
        $result = [
            'width' => null,
            'height' => null,
            'takenAt' => null,
            'cameraModel' => null,
            'gpsLat' => null,
            'gpsLon' => null,
            'aperture' => null,
            'shutterSpeed' => null,
            'iso' => null,
            'focalLength' => null,
            'lens' => null,
        ];

        if (!function_exists('exif_read_data') || !file_exists($absolutePath)) {
            return $result;
        }

        $exif = @exif_read_data($absolutePath, null, false);

        if ($exif === false) {
            return $result;
        }

        $result['width'] = isset($exif['COMPUTED']['Width']) ? (int) $exif['COMPUTED']['Width'] : null;
        $result['height'] = isset($exif['COMPUTED']['Height']) ? (int) $exif['COMPUTED']['Height'] : null;

        if (!empty($exif['DateTimeOriginal'])) {
            try {
                $result['takenAt'] = new \DateTimeImmutable($exif['DateTimeOriginal']);
            } catch (\Exception) {
                // date EXIF invalide — on ignore
            }
        }

        $make = $exif['Make'] ?? null;
        $model = $exif['Model'] ?? null;
        if ($make || $model) {
            $result['cameraModel'] = trim(($make ?? '').' '.($model ?? ''));
        }

        if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude'])) {
            $result['gpsLat'] = number_format($this->gpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N'), 7);
            $result['gpsLon'] = number_format($this->gpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E'), 7);
        }

        // Réglages de prise de vue (pack photographe) : rationnels bruts mis en
        // forme par ExifValueFormatter.
        $result['aperture'] = $this->formatter->fNumber(isset($exif['FNumber']) ? (string) $exif['FNumber'] : null);
        $result['shutterSpeed'] = $this->formatter->exposure(isset($exif['ExposureTime']) ? (string) $exif['ExposureTime'] : null);
        $result['focalLength'] = $this->formatter->focalLength(isset($exif['FocalLength']) ? (string) $exif['FocalLength'] : null);

        if (isset($exif['ISOSpeedRatings'])) {
            $iso = is_array($exif['ISOSpeedRatings']) ? ($exif['ISOSpeedRatings'][0] ?? null) : $exif['ISOSpeedRatings'];
            $result['iso'] = null !== $iso ? (int) $iso : null;
        }

        $lens = $exif['UndefinedTag:0xA434'] ?? $exif['LensModel'] ?? null;
        if (is_string($lens) && '' !== trim($lens)) {
            $result['lens'] = trim($lens);
        }

        return $result;
    }

    /**
     * Convertit les données GPS EXIF (degrés/minutes/secondes) en degrés décimaux.
     *
     * @param array<string> $gpsData Tableau [degrés, minutes, secondes] en format "num/denom"
     */
    private function gpsToDecimal(array $gpsData, string $ref): float
    {
        $toFloat = static fn (string $v): float => array_sum(array_map(
            static fn ($p) => (float) $p,
            explode('/', $v)
        )) ?: (float) $v;

        $deg = $toFloat($gpsData[0]);
        $min = $toFloat($gpsData[1]) / 60;
        $sec = $toFloat($gpsData[2]) / 3600;
        $decimal = $deg + $min + $sec;

        return in_array(strtoupper($ref), ['S', 'W'], true) ? -$decimal : $decimal;
    }
}
