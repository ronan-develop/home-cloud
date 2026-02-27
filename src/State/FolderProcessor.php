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
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly FolderRepository $folderRepository,
        private readonly UserRepository $userRepository,
        /** Injecté pour convertir l'entité en DTO après persist (évite la duplication du mapping) */
        private readonly FolderProvider $provider,
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

        $owner = $this->userRepository->find($data->ownerId)
            ?? throw new NotFoundHttpException('User not found');

        $parent = null;
        if ($data->parentId !== null) {
            $parent = $this->folderRepository->find($data->parentId)
                ?? throw new NotFoundHttpException('Parent folder not found');
        }

        $folder = new Folder($data->name, $owner, $parent);
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

        if ($data->name !== '') {
            $folder->setName($data->name);
        }

        if (array_key_exists('parentId', (array) $data)) {
            if ($data->parentId !== null && $data->parentId === $uriVariables['id']) {
                throw new BadRequestHttpException('A folder cannot be its own parent');
            }
            $parent = $data->parentId !== null
                ? ($this->folderRepository->find($data->parentId) ?? throw new NotFoundHttpException('Parent folder not found'))
                : null;
            $folder->setParent($parent);
        }

        $this->em->flush();

        return $this->provider->toOutput($folder);
    }

    /**
     * DELETE /api/v1/folders/{id} — supprime le dossier.
     * Retourne null → API Platform génère une réponse 204 No Content.
     */
    private function handleDelete(array $uriVariables): null
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');

        $this->em->remove($folder);
        $this->em->flush();

        return null;
    }
}
