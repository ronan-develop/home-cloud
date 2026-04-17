<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\ApiResource\FolderOutput;
use App\Enum\FolderMediaType;
use App\Interface\FolderRepositoryInterface;
use App\Interface\UserRepositoryInterface;
use App\Service\FolderService;
use App\Service\IriExtractor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dispatcher HTTP → FolderService pour les opérations d'écriture (POST, PATCH, DELETE).
 *
 * Rôle : couche transport uniquement.
 * - Reçoit le FolderOutput désérialisé depuis le corps JSON
 * - Résout les entités (owner, parent) depuis les IDs/IRIs
 * - Délègue toute la logique métier à FolderService
 * - Retourne le DTO mis à jour (ou null pour 204 sur DELETE)
 *
 * @implements ProcessorInterface<FolderOutput, FolderOutput|null>
 */
final class FolderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FolderService $folderService,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
        /** Injecté pour convertir l'entité en DTO après persist (évite la duplication du mapping) */
        private readonly FolderProvider $provider,
        private readonly RequestStack $requestStack,
        private readonly IriExtractor $iriExtractor,
        private readonly ValidatorInterface $validator,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$operation instanceof Delete) {
            $this->validator->validate($data, $operation->getValidationContext() ?? []);
        }

        return match (true) {
            $operation instanceof Post   => $this->handlePost($data),
            $operation instanceof Patch  => $this->handlePatch($data, $uriVariables),
            $operation instanceof Delete => $this->handleDelete($uriVariables),
            default => $data,
        };
    }

    /**
     * POST /api/v1/folders — résout owner + parent, délègue la création à FolderService.
     */
    private function handlePost(FolderOutput $data): FolderOutput
    {
        if (empty($data->ownerId)) {
            throw new BadRequestHttpException('ownerId is required');
        }

        $owner = $this->userRepository->find($data->ownerId)
            ?? throw new NotFoundHttpException('User not found');

        $parent = null;
        if ($data->parentId !== null) {
            $parentId = $this->iriExtractor->extractUuid($data->parentId);
            $parent   = $this->folderRepository->find($parentId)
                ?? throw new NotFoundHttpException('Parent folder not found');
        }

        $mediaType = FolderMediaType::General;
        if ($data->mediaType !== 'general') {
            $mediaType = FolderMediaType::tryFrom($data->mediaType)
                ?? throw new BadRequestHttpException('Invalid mediaType: ' . $data->mediaType);
        }

        $folder = $this->folderService->createFolder($owner, $data->name, $parent, $mediaType);
        return $this->provider->toOutput($folder);
    }

    /**
     * PATCH /api/v1/folders/{id} — résout les entités, délègue la mise à jour à FolderService.
     * Lit le corps JSON brut pour distinguer parentId absent/null/valeur.
     */
    private function handlePatch(FolderOutput $data, array $uriVariables): FolderOutput
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');

        $newMediaType = null;
        if ($data->mediaType !== 'general') {
            $newMediaType = FolderMediaType::tryFrom($data->mediaType)
                ?? throw new BadRequestHttpException('Invalid mediaType: ' . $data->mediaType);
        }

        $body          = json_decode($this->requestStack->getCurrentRequest()?->getContent() ?? '{}', true);
        $parentChanged = array_key_exists('parentId', $body ?? []);
        $newParent     = null;

        if ($parentChanged && $body['parentId'] !== null) {
            $parentId  = $this->iriExtractor->extractUuid($body['parentId']);
            $newParent = $this->folderRepository->find($parentId)
                ?? throw new NotFoundHttpException('Parent folder not found');
        }

        $this->folderService->updateFolder($folder, $data->name, $newMediaType, $parentChanged, $newParent);
        return $this->provider->toOutput($folder);
    }

    /**
     * DELETE /api/v1/folders/{id} — lit le flag deleteContents, délègue à FolderService.
     * Retourne null → API Platform génère une réponse 204 No Content.
     */
    private function handleDelete(array $uriVariables): null
    {
        $folder = $this->folderRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('Folder not found');

        $content        = $this->requestStack->getCurrentRequest()?->getContent() ?? '{}';
        $body           = json_decode($content, true);
        $deleteContents = !is_array($body) || !array_key_exists('deleteContents', $body)
            || (bool) $body['deleteContents'];

        $this->folderService->deleteFolder($folder, $deleteContents);
        return null;
    }
}

