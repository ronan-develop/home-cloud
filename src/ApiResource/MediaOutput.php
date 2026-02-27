<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\MediaProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * DTO lecture seule pour la ressource Media.
 *
 * Rôle : exposer les métadonnées enrichies d'un fichier média (EXIF, dimensions,
 * GPS, thumbnail) sans exposer l'entité Doctrine directement.
 *
 * Choix :
 * - Pas de POST/PATCH/DELETE : Media est créé automatiquement par le handler
 *   Messenger après upload — il n'est pas manipulable directement via l'API.
 * - SKIP_NULL_VALUES => false : tous les champs sont toujours présents en réponse
 *   (même null) pour que le client sache ce qui existe vs ce qui est absent.
 * - Filtre ?type= sur la collection pour distinguer photos et vidéos.
 */
#[ApiResource(
    shortName: 'Media',
    normalizationContext: [
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
    operations: [
        new Get(
            uriTemplate: '/v1/medias/{id}',
            provider: MediaProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/v1/medias',
            provider: MediaProvider::class,
        ),
    ],
)]
final class MediaOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $mediaType,
        public readonly string $fileId,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $takenAt,
        public readonly ?string $gpsLat,
        public readonly ?string $gpsLon,
        public readonly ?string $cameraModel,
        public readonly ?string $thumbnailPath,
        public readonly string $createdAt,
    ) {}
}
