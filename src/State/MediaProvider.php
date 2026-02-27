<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MediaOutput;
use App\Entity\Media;
use App\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Fournit les données lues pour les opérations GET sur la ressource Media.
 *
 * Rôle : couche lecture — transforme les entités Media en DTOs MediaOutput
 * sans jamais exposer les entités Doctrine directement.
 *
 * Supporte le filtre ?type= (photo|video) sur la collection.
 *
 * @implements ProviderInterface<MediaOutput>
 */
final class MediaProvider implements ProviderInterface
{
    public function __construct(
        private readonly MediaRepository $repository,
        private readonly RequestStack $requestStack,
    ) {}

    /** @return MediaOutput|MediaOutput[]|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $media = $this->repository->find(Uuid::fromString($uriVariables['id']))
                ?? throw new NotFoundHttpException('Media not found');

            return $this->toOutput($media);
        }

        $type = $this->requestStack->getCurrentRequest()?->query->get('type');
        $criteria = $type !== null ? ['mediaType' => $type] : [];

        return array_map(
            fn (Media $m) => $this->toOutput($m),
            $this->repository->findBy($criteria, ['createdAt' => 'DESC']),
        );
    }

    public function toOutput(Media $media): MediaOutput
    {
        return new MediaOutput(
            id: (string) $media->getId(),
            mediaType: $media->getMediaType(),
            fileId: (string) $media->getFile()->getId(),
            width: $media->getWidth(),
            height: $media->getHeight(),
            takenAt: $media->getTakenAt()?->format(\DateTimeInterface::ATOM),
            gpsLat: $media->getGpsLat(),
            gpsLon: $media->getGpsLon(),
            cameraModel: $media->getCameraModel(),
            thumbnailUrl: $media->getThumbnailPath() !== null
                ? '/api/v1/medias/'.$media->getId().'/thumbnail'
                : null,
            createdAt: $media->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
