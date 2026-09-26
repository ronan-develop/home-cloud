<?php

declare(strict_types=1);

namespace App\Interface;

/**
 * Résout la date de prise de vue d'un média entre plusieurs sources
 * possibles (EXIF embarqué, métadonnées Google Takeout, futures sources).
 * Isolé en Strategy pour qu'une nouvelle source s'ajoute sans modifier
 * les appelants (#327).
 */
interface MediaDateResolverInterface
{
    public function resolve(?\DateTimeImmutable $exifDate, ?\DateTimeImmutable $takeoutDate): ?\DateTimeImmutable;
}
