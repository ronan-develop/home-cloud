<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Met en forme les valeurs EXIF brutes (rationnels "num/denom", décimaux) en
 * chaînes lisibles pour un photographe.
 *
 * Isolé d'ExifService : pur (aucune dépendance à l'extension EXIF), donc
 * testable sans fichier image. Les valeurs sont exposées telles que le boîtier
 * les encode — la vitesse garde sa fraction ("1/250" plutôt que "0.004"),
 * l'ouverture reste un nombre simple.
 */
final class ExifValueFormatter
{
    /**
     * Ouverture (f-number) → nombre décimal court : "28/10" → "2.8".
     */
    public function fNumber(?string $raw): ?string
    {
        $value = $this->toFloat($raw);

        return null === $value ? null : $this->trimDecimal($value);
    }

    /**
     * Longueur focale (mm) → nombre court : "50/1" → "50".
     */
    public function focalLength(?string $raw): ?string
    {
        $value = $this->toFloat($raw);

        return null === $value ? null : $this->trimDecimal($value);
    }

    /**
     * Vitesse d'obturation : sous la seconde, on garde la fraction ("1/250") ;
     * à une seconde ou plus, on affiche des secondes décimales ("2", "1.3").
     */
    public function exposure(?string $raw): ?string
    {
        $value = $this->toFloat($raw);

        if (null === $value || $value <= 0.0) {
            return null;
        }

        if ($value < 1.0) {
            return '1/'.(int) round(1 / $value);
        }

        return $this->trimDecimal($value);
    }

    /**
     * Convertit une valeur EXIF ("num/denom" ou décimal) en float, ou null.
     */
    private function toFloat(?string $raw): ?float
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (str_contains($raw, '/')) {
            [$numerator, $denominator] = array_pad(explode('/', $raw, 2), 2, '1');

            if (!is_numeric($numerator) || !is_numeric($denominator) || 0.0 === (float) $denominator) {
                return null;
            }

            return (float) $numerator / (float) $denominator;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * Formate un float sans zéros superflus : 2.80 → "2.8", 4.0 → "4".
     */
    private function trimDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
