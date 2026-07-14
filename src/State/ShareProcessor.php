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
use App\Interface\ShareRepositoryInterface;
use App\Interface\UserRepositoryInterface;
use App\Security\OwnershipChecker;
use App\Security\ResourceLocator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        private readonly ShareRepositoryInterface $shareRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ShareProvider $provider,
        private readonly Security $security,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly ResourceLocator $resourceLocator,
        private readonly RateLimiterFactory $shareCreationLimiter,
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

        $limiter = $this->shareCreationLimiter->create((string) $owner->getId());
        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Trop de partages créés récemment. Réessayez plus tard.');
        }

        $guest = $this->resolveGuest($data);

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

        if ($guest->getId()->equals($owner->getId())) {
            throw new BadRequestHttpException('Vous ne pouvez pas partager une ressource avec vous-même.');
        }

        $resource = $this->resourceLocator->locate($resourceType, Uuid::fromString($resourceId));
        $this->ownershipChecker->denyUnlessOwner($resource);

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
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Un partage identique existe déjà pour cet utilisateur et cette ressource.');
        }

        return $this->provider->toOutput($share);
    }

    private function resolveGuest(ShareOutput $data): User
    {
        $guestId = $data->guestId ?? '';
        if ($guestId !== '') {
            if (!Uuid::isValid($guestId)) {
                throw new BadRequestHttpException('guestId invalide.');
            }
            $guest = $this->userRepository->find(Uuid::fromString($guestId));
            if ($guest === null) {
                throw new NotFoundHttpException('Utilisateur invité introuvable.');
            }

            return $guest;
        }

        $guestEmail = $data->guestEmail ?? '';
        if ($guestEmail !== '') {
            $guest = $this->userRepository->findOneBy(['email' => $guestEmail]);
            if ($guest === null) {
                throw new NotFoundHttpException('Aucun compte HomeCloud n\'est associé à cet email.');
            }

            return $guest;
        }

        throw new BadRequestHttpException('guestId ou guestEmail requis.');
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

        $this->ownershipChecker->denyUnlessOwner($share);

        return $share;
    }
}
