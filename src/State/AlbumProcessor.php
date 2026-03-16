<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AlbumOutput;
use App\Entity\Album;
use App\Repository\AlbumRepository;
use App\Interface\UserRepositoryInterface;
use App\Service\AuthenticationResolver;
use App\Service\FilenameValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite les opérations d'écriture sur la ressource Album (POST, PATCH, DELETE).
 *
 * @implements ProcessorInterface<AlbumOutput, AlbumOutput|null>
 */
final class AlbumProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlbumRepository $albumRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AlbumProvider $provider,
        private readonly AuthenticationResolver $authResolver,
        private readonly LoggerInterface $logger,
        private readonly FilenameValidator $filenameValidator,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return match (true) {
            $operation instanceof Post   => $this->handlePost($data),
            $operation instanceof Patch  => $this->handlePatch($data, $uriVariables),
            $operation instanceof Delete => $this->handleDelete($uriVariables),
            default => $data,
        };
    }

    private function handlePost(AlbumOutput $data): AlbumOutput
    {
        if (empty($data->name)) {
            throw new BadRequestHttpException('name is required');
        }

        if (empty($data->ownerId)) {
            throw new BadRequestHttpException('ownerId is required');
        }

        $owner = $this->userRepository->find($data->ownerId)
            ?? throw new NotFoundHttpException('User not found');

        $album = new Album($data->name, $owner);
        $this->em->persist($album);
        $this->em->flush();

        return $this->provider->toOutput($album);
    }

    private function handlePatch(AlbumOutput $data, array $uriVariables): AlbumOutput
    {
        $album = $this->albumRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Album not found');
        // Ownership : seul le propriétaire peut modifier
        $user = $this->authResolver->getAuthenticatedUser();
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedHttpException('You must be authenticated');
        }
        if ((string) $user->getId() !== (string) $album->getOwner()->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this album');
        }
        if ($data->name !== '') {
            $this->filenameValidator->validate($data->name);
            $album->setName($data->name);
        }
        $this->em->flush();
        return $this->provider->toOutput($album);
    }

    private function handleDelete(array $uriVariables): null
    {
        $album = $this->albumRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Album not found');
        // Ownership : seul le propriétaire peut supprimer
        $user = $this->authResolver->getAuthenticatedUser();
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedHttpException('You must be authenticated');
        }
        if ((string) $user->getId() !== (string) $album->getOwner()->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this album');
        }
        $this->em->remove($album);
        $this->em->flush();
        return null;
    }
}
