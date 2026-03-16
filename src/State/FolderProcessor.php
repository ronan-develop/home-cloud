<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FolderOutput;
use App\Dto\DeleteFolderInput;
use App\Entity\Folder;
use App\Enum\FolderMediaType;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\FolderRepositoryInterface;
use App\Interface\UserRepositoryInterface;
use App\Security\OwnershipChecker;
use App\Service\AuthenticationResolver;
use App\Service\FilenameValidator;
use App\Service\IriExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
        /** Injecté pour convertir l'entité en DTO après persist (évite la duplication du mapping) */
        private readonly FolderProvider $provider,
        private readonly RequestStack $requestStack,
        private readonly AuthenticationResolver $authResolver,
        private readonly LoggerInterface $logger,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly FilenameValidator $filenameValidator,
        private readonly IriExtractor $iriExtractor,
        private readonly OwnershipChecker $ownershipChecker,
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
        $this->filenameValidator->validate($data->name);
        $owner = $this->userRepository->find($data->ownerId)
            ?? throw new NotFoundHttpException('User not found');
        $parent = null;
        if ($data->parentId !== null) {
            // Extraire l'UUID depuis l'IRI si nécessaire
            $parentId = $this->iriExtractor->extractUuid($data->parentId);
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
        $user = $this->authResolver->getAuthenticatedUser();
        $this->logger->info('Folder PATCH operation initiated', [
            'folder_id' => (string) $folder->getId(),
            'user_id'   => $user ? (string) $user->getId() : null,
        ]);
        $this->ownershipChecker->denyUnlessOwner($folder);
        if ($data->name !== '') {
            $this->filenameValidator->validate($data->name);
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
                $parentId = $this->iriExtractor->extractUuid($body['parentId']);
                if ($parentId === (string) $uriVariables['id']) {
                    throw new BadRequestHttpException('A folder cannot be its own parent');
                }
                $parent = $this->folderRepository->find($parentId)
                    ?? throw new NotFoundHttpException('Parent folder not found');
                // Sécurité : ownership du parent
                if (!$this->ownershipChecker->isOwner($parent)) {
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
     * Lit le body JSON pour l'option deleteContents (défaut: true).
     * Si deleteContents=false : déplace tous les fichiers vers le dossier Uploads avant suppression.
     * Retourne null → API Platform génère une réponse 204 No Content.
     */
    private function handleDelete(array $uriVariables): null
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');
        $this->ownershipChecker->denyUnlessOwner($folder);
        $user = $this->authResolver->getAuthenticatedUser();

        $input = new DeleteFolderInput();
        $content = $this->requestStack->getCurrentRequest()?->getContent() ?? '{}';
        $body = json_decode($content, true);
        if (is_array($body) && array_key_exists('deleteContents', $body)) {
            $input->deleteContents = (bool) $body['deleteContents'];
        }

        if (!$input->deleteContents) {
            $uploadsFolder = $this->defaultFolderService->resolve(null, null, $user);

            $descendantIds = $this->folderRepository->findDescendantIds($folder);
            $allFolderIds = array_merge([$folder->getId()->toRfc4122()], $descendantIds);

            foreach ($allFolderIds as $folderId) {
                $f = $this->folderRepository->find($folderId);
                if ($f === null) {
                    continue;
                }
                foreach ($f->getFiles() as $file) {
                    $file->setFolder($uploadsFolder);
                }
            }

            // Persiste le dossier Uploads (création lazy) et les déplacements de fichiers
            $this->em->flush();

            // Supprime les sous-dossiers puis le dossier lui-même.
            // refresh() force le rechargement depuis la DB : la collection files est vide
            // (fichiers déplacés), donc cascade: remove ne supprimera pas les fichiers déplacés.
            foreach ($descendantIds as $descId) {
                $desc = $this->folderRepository->find($descId);
                if ($desc !== null) {
                    $this->em->refresh($desc);
                    $this->em->remove($desc);
                }
            }
            $this->em->refresh($folder);
            $this->em->remove($folder);
            $this->em->flush();

            return null;
        }

        $this->em->remove($folder);
        $this->em->flush();
        return null;
    }
}
