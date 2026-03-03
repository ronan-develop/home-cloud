<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FolderOutput;
use App\Entity\Folder;
use App\Enum\FolderMediaType;
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Traite les opérations d'écriture sur la ressource Folder (POST, PATCH, DELETE).
 *
 * Rôle : couche d'écriture — reçoit un FolderOutput désérialisé depuis le corps
 * de la requête, applique les règles métier, persiste via Doctrine, puis retourne
 * le DTO mis à jour (sauf DELETE qui retourne null → 204).
 *
 * Choix :
 * - Le dispatch par type d'opération (match + instanceof) évite un processeur
 *   par opération tout en gardant chaque handler focalisé.
 * - FolderProvider est injecté pour réutiliser toOutput() après persist,
 *   garantissant une sérialisation cohérente entre lecture et écriture.
 * - Les exceptions Symfony (BadRequest, NotFound) sont automatiquement
 *   converties en réponses JSON avec le bon code HTTP par API Platform.
 *
 * @implements ProcessorInterface<FolderOutput, FolderOutput|null>
 */
final class FolderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FolderRepository $folderRepository,
        private readonly UserRepository $userRepository,
        /** Injecté pour convertir l'entité en DTO après persist (évite la duplication du mapping) */
        private readonly FolderProvider $provider,
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage, // ✅ Ajouté
        private readonly LoggerInterface $logger, // ✅ Ajouté
    ) {}

    /**
     * Point d'entrée unique : délègue au bon handler selon le type d'opération.
     * $data est le FolderOutput désérialisé depuis le corps JSON de la requête.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return match (true) {
            $operation instanceof Post   => $this->handlePost($data),
            $operation instanceof Patch  => $this->handlePatch($data, $uriVariables),
            $operation instanceof Delete => $this->handleDelete($uriVariables),
            default => $data,
        };
    }

    /**
     * POST /api/v1/folders — crée un dossier.
     * Champs requis : name, ownerId. Champ optionnel : parentId.
     */
    private function handlePost(FolderOutput $data): FolderOutput
    {
        if (empty($data->name)) {
            throw new BadRequestHttpException('name is required');
        }
        if (empty($data->ownerId)) {
            throw new BadRequestHttpException('ownerId is required');
        }
        if (!preg_match('/^[^\\\\\/\:\*\?"<>|]+$/u', $data->name)) {
            throw new BadRequestHttpException('Invalid characters in folder name');
        }
        $owner = $this->userRepository->find($data->ownerId)
            ?? throw new NotFoundHttpException('User not found');
        $parent = null;
        if ($data->parentId !== null) {
            // Extraire l'UUID de l'IRI si nécessaire
            $parentId = $data->parentId;
            if (strpos($parentId, '/') !== false) {
                $parentId = basename($parentId);
            }
            $parent = $this->folderRepository->find($parentId)
                ?? throw new NotFoundHttpException('Parent folder not found');
        }
        // Unicité du nom dans le parent pour ce propriétaire
        $criteria = ['name' => $data->name, 'owner' => $owner];
        if ($parent) {
            $criteria['parent'] = $parent;
        } else {
            $criteria['parent'] = null;
        }
        if ($this->folderRepository->findOneBy($criteria)) {
            throw new BadRequestHttpException('A folder with this name already exists in the parent');
        }
        $mediaType = FolderMediaType::General;
        if ($data->mediaType !== 'general') {
            $mediaType = FolderMediaType::tryFrom($data->mediaType)
                ?? throw new BadRequestHttpException('Invalid mediaType: ' . $data->mediaType);
        }
        $folder = new Folder($data->name, $owner, $parent);
        $folder->setMediaType($mediaType);
        $this->em->persist($folder);
        $this->em->flush();
        return $this->provider->toOutput($folder);
    }

    /**
     * PATCH /api/v1/folders/{id} — mise à jour partielle.
     * Seuls les champs présents dans le corps sont modifiés.
     */
    private function handlePatch(FolderOutput $data, array $uriVariables): FolderOutput
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');
        // Ownership : seul le propriétaire peut modifier
        $user = $this->getAuthenticatedUser();
        $this->logger->info('🔍 PATCH Ownership Check', [
            'folder_id' => (string) $folder->getId(),
            'folder_owner_id' => (string) $folder->getOwner()->getId(),
            'folder_owner_email' => $folder->getOwner()->getEmail(),
            'current_user' => $user ? get_class($user) : 'null',
            'current_user_id' => $user ? (string) $user->getId() : 'null',
            'current_user_email' => $user?->getEmail(),
            'ids_match' => $user ? (string) $user->getId() === (string) $folder->getOwner()->getId() : false,
        ]);
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedHttpException('You must be authenticated');
        }
        if ((string) $user->getId() !== (string) $folder->getOwner()->getId()) {
            throw new AccessDeniedHttpException('You are not the owner of this folder');
        }
        if ($data->name !== '') {
            // Correction : doublement des antislashs pour l'expression régulière
            if (!preg_match('/^[^\\\\\/\:\*\?\"\<\>\|]+$/u', $data->name)) {
                throw new BadRequestHttpException('Invalid characters in folder name');
            }
            // Unicité du nom dans le parent pour ce propriétaire
            $parent = $folder->getParent();
            $criteria = ['name' => $data->name, 'owner' => $folder->getOwner()];
            $criteria['parent'] = $parent;
            $existing = $this->folderRepository->findOneBy($criteria);
            if ($existing && !$existing->getId()->equals($folder->getId())) {
                throw new BadRequestHttpException('A folder with this name already exists in the parent');
            }
            $folder->setName($data->name);
        }
        if ($data->mediaType !== 'general') {
            $mediaType = FolderMediaType::tryFrom($data->mediaType)
                ?? throw new BadRequestHttpException('Invalid mediaType: ' . $data->mediaType);
            $folder->setMediaType($mediaType);
        }
        // Lecture JSON brute pour distinguer parentId absent/null
        $body = json_decode(
            $this->requestStack->getCurrentRequest()?->getContent() ?? '{}',
            true
        );
        if (array_key_exists('parentId', $body ?? [])) {
            if ($body['parentId'] !== null) {
                $parentId = $body['parentId'];
                if (strpos($parentId, '/') !== false) {
                    $parentId = basename($parentId);
                }
                if ($parentId === (string) $uriVariables['id']) {
                    throw new BadRequestHttpException('A folder cannot be its own parent');
                }
                $parent = $this->folderRepository->find($parentId)
                    ?? throw new NotFoundHttpException('Parent folder not found');
                // Sécurité : ownership du parent
                if ((string) $parent->getOwner()->getId() !== (string) $user->getId()) {
                    throw new AccessDeniedHttpException('You do not own the target parent folder');
                }
                // Détection de cycle profond
                if ($this->wouldCreateCycle($folder, $parent)) {
                    throw new BadRequestHttpException('Moving this folder would create a cycle');
                }
            } else {
                $parent = null; // Mise à la racine
            }
            $folder->setParent($parent);
        }
        $this->em->flush();
        return $this->provider->toOutput($folder);
    }

    /**
     * Vérifie si définir $newParent comme parent de $folder créerait un cycle.
     * Utilise FolderRepository::findAncestorIds() (CTE récursif, 1 seule requête SQL)
     * pour récupérer tous les ancêtres de $newParent.
     * Si $folder apparaît parmi ces ancêtres → cycle → retourne true.
     */
    private function wouldCreateCycle(Folder $folder, Folder $newParent): bool
    {
        $ancestorIds = $this->folderRepository->findAncestorIds($newParent);
        $folderId    = strtolower(str_replace('-', '', (string) $folder->getId()));
        foreach ($ancestorIds as $ancestorId) {
            if (strtolower(str_replace('-', '', $ancestorId)) === $folderId) {
                return true;
            }
        }
        return false;
    }

    /**
     * DELETE /api/v1/folders/{id} — supprime le dossier.
     * Retourne null → API Platform génère une réponse 204 No Content.
     */
    private function handleDelete(array $uriVariables): null
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');
        // Ownership : seul le propriétaire peut supprimer
        $user = $this->getAuthenticatedUser();
        if (!$user || !$folder->getOwner()->getId()->equals($user->getId())) {
            throw new AccessDeniedHttpException('You are not the owner of this folder');
        }
        $this->em->remove($folder);
        $this->em->flush();
        return null;
    }

    /**
     * Récupère l'utilisateur authentifié depuis le token de sécurité (API Platform context)
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
}
