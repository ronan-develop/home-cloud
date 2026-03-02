use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite les opérations d'écriture sur la ressource Album (POST, PATCH, DELETE).
 *
 * @implements ProcessorInterface<AlbumOutput, AlbumOutput|null>
 */
final class AlbumProcessor implements ProcessorInterface
{
    /**
     * Pattern recommandé : injection du TokenStorageInterface et LoggerInterface pour obtenir l'utilisateur courant de façon fiable (test/prod).
     * Voir FolderProcessor pour l'implémentation complète.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlbumRepository $albumRepository,
        private readonly UserRepository $userRepository,
        private readonly AlbumProvider $provider,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Récupère l'utilisateur authentifié depuis le TokenStorage (pattern commun).
     */
    private function getAuthenticatedUser(): ?\App\Entity\User
    {
        $token = $this->tokenStorage->getToken();
        $this->logger->info('🔍 TokenStorage State', [
            'has_token' => $token !== null,
            'token_class' => $token ? get_class($token) : 'null',
        ]);
        if ($token === null) {
            $this->logger->warning('⚠️ No token in TokenStorage');
            return null;
        }
        $user = $token->getUser();
        $this->logger->info('🔍 User from Token', [
            'user_class' => $user ? get_class($user) : 'null',
            'is_user_instance' => $user instanceof \App\Entity\User,
        ]);
        if ($user instanceof \App\Entity\User) {
            return $user;
        }
        if (is_string($user) && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $this->logger->info('🔍 User is string, searching by email', ['email' => $user]);
            return $this->userRepository->findOneBy(['email' => $user]);
        }
        $this->logger->warning('⚠️ User type not recognized', [
            'type' => gettype($user),
            'value' => $user,
        ]);
        return null;
    }

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

        if ($data->name !== '') {
            $album->setName($data->name);
        }

        $this->em->flush();

        return $this->provider->toOutput($album);
    }

    private function handleDelete(array $uriVariables): null
    {
        $album = $this->albumRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Album not found');

        $this->em->remove($album);
        $this->em->flush();

        return null;
    }
}
