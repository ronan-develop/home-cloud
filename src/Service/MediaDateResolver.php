<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\MediaDateResolverInterface;

/**
 * #327 — priorité Google Takeout sur EXIF : Google peut réencoder l'image
 * lors de l'export et perdre l'EXIF d'origine, la métadonnée Takeout
 * (photoTakenTime.timestamp) reste alors la seule source fiable.
 */
final class MediaDateResolver implements MediaDateResolverInterface
{
    public function resolve(?\DateTimeImmutable $exifDate, ?\DateTimeImmutable $takeoutDate): ?\DateTimeImmutable
    {
        return $takeoutDate ?? $exifDate;
    }
}
