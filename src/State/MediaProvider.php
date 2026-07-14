<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MediaOutput;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\User;
use App\Repository\MediaRepository;
use App\Security\ResourceAccessChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        private readonly Security $security,
        private readonly ResourceAccessChecker $resourceAccessChecker,
    ) {}

    /** @return MediaOutput|MediaOutput[]|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            throw new AccessDeniedHttpException();
        }

        if (isset($uriVariables['id'])) {
            $media = $this->repository->find(Uuid::fromString($uriVariables['id']))
                ?? throw new NotFoundHttpException('Media not found');

            if (!$this->resourceAccessChecker->canRead($currentUser, Share::RESOURCE_FILE, $media->getFile()->getId(), $media->getFile()->getOwner())) {
                throw new AccessDeniedHttpException();
            }

            return $this->toOutput($media);
        }

        $type = $this->requestStack->getCurrentRequest()?->query->get('type');

        if ($type !== null) {
            $allowed = ['photo', 'video', 'audio', 'document'];
            if (!in_array($type, $allowed, true)) {
                throw new BadRequestHttpException(
                    sprintf('Invalid type "%s". Allowed values: %s.', $type, implode(', ', $allowed))
                );
            }
        }

        return array_map(
            fn (Media $m) => $this->toOutput($m),
            $this->repository->findByOwner($currentUser, $type),
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
