<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Parse un fichier <nom>.supplemental-metadata.json d'un export Google
 * Takeout — jamais bloquant : absence ou malformation renvoie null,
 * l'EXIF embarqué reste le filet de sécurité (cf. MediaDateResolver).
 */
class TakeoutMetadataReader
{
    public function read(string $jsonPath): ?TakeoutMetadata
    {
        if (!is_file($jsonPath)) {
            return null;
        }

        $content = file_get_contents($jsonPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        $takenAt = null;
        $timestamp = $data['photoTakenTime']['timestamp'] ?? null;
        if (is_numeric($timestamp)) {
            $takenAt = new \DateTimeImmutable('@' . $timestamp);
        }

        $latitude = $data['geoData']['latitude'] ?? null;
        $longitude = $data['geoData']['longitude'] ?? null;

        // Google encode l'absence de géolocalisation par (0.0, 0.0), pas
        // null — un point réel existe à ces coordonnées (golfe de Guinée)
        // mais n'a jamais de sens pour une photo personnelle. Comparaison
        // numérique large : json_decode peut renvoyer un int 0 ou un float
        // 0.0 selon la représentation JSON d'origine.
        if (is_numeric($latitude) && is_numeric($longitude) && (float) $latitude === 0.0 && (float) $longitude === 0.0) {
            $latitude = null;
            $longitude = null;
        }

        return new TakeoutMetadata(
            takenAt: $takenAt,
            latitude: is_float($latitude) ? $latitude : (is_numeric($latitude) ? (float) $latitude : null),
            longitude: is_float($longitude) ? $longitude : (is_numeric($longitude) ? (float) $longitude : null),
        );
    }
}
