<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ShareOutput;
use App\Entity\Share;
use App\Entity\User;
use App\Repository\ShareRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fournit les données pour les opérations GET sur la ressource Share.
 *
 * - GetCollection : retourne les partages où l'utilisateur courant est owner OU guest.
 * - Get/{id} : retourne le partage uniquement si l'utilisateur est owner ou guest.
 *
 * @implements ProviderInterface<ShareOutput>
 */
final class ShareProvider implements ProviderInterface
{
    public function __construct(
        private readonly ShareRepository $repository,
        private readonly Pagination $pagination,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (isset($uriVariables['id'])) {
            $share = $this->repository->find($uriVariables['id']);
            if ($share === null) {
                return null;
            }
            if (!$this->isParticipant($share, $currentUser)) {
                throw new AccessDeniedHttpException('Accès interdit à ce partage.');
            }
            return $this->toOutput($share);
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);
        $total = $this->repository->countByUser($currentUser);
        $items = array_map($this->toOutput(...), $this->repository->findByUser($currentUser, $limit, $offset));

        return new TraversablePaginator(new \ArrayIterator($items), $page, $limit, $total);
    }

    public function toOutput(Share $share): ShareOutput
    {
        $output = new ShareOutput();
        $output->id = $share->getId()->toRfc4122();
        $output->ownerId = $share->getOwner()->getId()->toRfc4122();
        $output->guestId = $share->getGuest()->getId()->toRfc4122();
        $output->resourceType = $share->getResourceType();
        $output->resourceId = $share->getResourceId()->toRfc4122();
        $output->permission = $share->getPermission();
        $output->expiresAt = $share->getExpiresAt()?->format(\DateTimeInterface::ATOM);
        $output->createdAt = $share->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $output;
    }

    private function isParticipant(Share $share, User $user): bool
    {
        return $share->getOwner()->getId()->equals($user->getId())
            || $share->getGuest()->getId()->equals($user->getId());
    }
}
