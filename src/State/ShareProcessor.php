<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ShareOutput;
use App\Entity\Share;
use App\Entity\User;
use App\Repository\ShareRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Traite les opérations d'écriture sur la ressource Share (POST, PATCH, DELETE).
 *
 * Règles :
 * - POST : le owner est toujours l'utilisateur JWT courant.
 * - PATCH/DELETE : réservés au owner (403 pour le guest).
 *
 * @implements ProcessorInterface<ShareOutput, ShareOutput|null>
 */
final class ShareProcessor implements ProcessorInterface
{
    private const VALID_TYPES = [Share::RESOURCE_FILE, Share::RESOURCE_FOLDER, Share::RESOURCE_ALBUM];
    private const VALID_PERMISSIONS = [Share::PERMISSION_READ, Share::PERMISSION_WRITE];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShareRepository $shareRepository,
        private readonly UserRepository $userRepository,
        private readonly ShareProvider $provider,
        private readonly Security $security,
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

    private function handlePost(ShareOutput $data): ShareOutput
    {
        /** @var User $owner */
        $owner = $this->security->getUser();

        $guestId = $data->guestId ?? '';
        if (!Uuid::isValid($guestId)) {
            throw new BadRequestHttpException('guestId invalide.');
        }
        $guest = $this->userRepository->find(Uuid::fromString($guestId));
        if ($guest === null) {
            throw new NotFoundHttpException('Utilisateur invité introuvable.');
        }

        $resourceType = $data->resourceType ?? '';
        if (!in_array($resourceType, self::VALID_TYPES, true)) {
            throw new BadRequestHttpException('resourceType invalide. Valeurs acceptées : file, folder, album.');
        }

        $resourceId = $data->resourceId ?? '';
        if (!Uuid::isValid($resourceId)) {
            throw new BadRequestHttpException('resourceId invalide.');
        }

        $permission = $data->permission ?? '';
        if (!in_array($permission, self::VALID_PERMISSIONS, true)) {
            throw new BadRequestHttpException('permission invalide. Valeurs acceptées : read, write.');
        }

        $expiresAt = null;
        if (!empty($data->expiresAt)) {
            $expiresAt = new \DateTimeImmutable($data->expiresAt);
        }

        $share = new Share(
            $owner,
            $guest,
            $resourceType,
            Uuid::fromString($resourceId),
            $permission,
            $expiresAt,
        );

        $this->em->persist($share);
        $this->em->flush();

        return $this->provider->toOutput($share);
    }

    private function handlePatch(ShareOutput $data, array $uriVariables): ShareOutput
    {
        $share = $this->getShareAsOwner($uriVariables['id']);

        if (!empty($data->permission)) {
            if (!in_array($data->permission, self::VALID_PERMISSIONS, true)) {
                throw new BadRequestHttpException('permission invalide. Valeurs acceptées : read, write.');
            }
            $share->setPermission($data->permission);
        }

        if (array_key_exists('expiresAt', (array) $data)) {
            $share->setExpiresAt($data->expiresAt !== null ? new \DateTimeImmutable($data->expiresAt) : null);
        }

        $this->em->flush();

        return $this->provider->toOutput($share);
    }

    private function handleDelete(array $uriVariables): null
    {
        $share = $this->getShareAsOwner($uriVariables['id']);
        $this->em->remove($share);
        $this->em->flush();

        return null;
    }

    private function getShareAsOwner(string $id): Share
    {
        $share = $this->shareRepository->find($id);
        if ($share === null) {
            throw new NotFoundHttpException('Partage introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$share->getOwner()->getId()->equals($currentUser->getId())) {
            throw new AccessDeniedHttpException('Seul le propriétaire peut modifier ou supprimer ce partage.');
        }

        return $share;
    }
}
