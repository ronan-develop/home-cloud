<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ApiResource\FileOutput;
use App\Interface\MediaProcessorInterface;
use App\Interface\UploadBatchRepositoryInterface;
use App\Message\MediaProcessMessage;
use App\Entity\UploadBatch;
use App\Service\CreateFileService;
use App\Service\PendingMediaProcessingCollector;
use App\State\FileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller dédié à l'upload de fichiers via multipart/form-data.
 *
 * Rôle : recevoir le binaire et les métadonnées en une seule requête POST,
 * puis déléguer le stockage et la persistance à CreateFileService.
 *
 * Pourquoi un controller séparé et non un Processor API Platform ?
 * API Platform ne supporte pas nativement multipart/form-data comme format
 * d'entrée désérialisable. En déclarant `deserialize: false` sur l'opération
 * POST + `controller: FileUploadController::class`, on bypasse la négociation
 * de contenu d'API Platform et on gère la requête directement.
 *
 * Le controller retourne un FileOutput qui est ensuite sérialisé normalement
 * par API Platform avec le bon status 201.
 */
#[AsController]
final class FileUploadController extends AbstractController
{
    public function __construct(
        private readonly CreateFileService $createFileService,
        private readonly FileProvider $provider,
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $bus,
        private readonly PendingMediaProcessingCollector $pendingMediaProcessingCollector,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly UploadBatchRepositoryInterface $uploadBatchRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * POST /api/v1/files — upload multipart/form-data.
     *
     * Champs form attendus :
     *   file           (fichier binaire, obligatoire)
     *   ownerId        (UUID utilisateur, obligatoire)
     *   folderId       (UUID folder existant, optionnel)
     *   newFolderName  (nom du nouveau folder à créer, optionnel)
     *   relativePath   (sous-arborescence à recréer sous le folder cible,
     *                   ex. "2026-07-10-BMA/scans" — import d'un dossier
     *                   local avec sa structure, #238)
     *
     * Priorité : folderId > newFolderName > dossier "Uploads", puis relativePath
     */
    public function __invoke(Request $request): Response
    {
        $uploadedFile = $request->files->get('file');
        if ($uploadedFile === null) {
            throw new BadRequestHttpException('A file must be uploaded (multipart field: "file")');
        }

        $ownerId = $request->request->get('ownerId');
        if (empty($ownerId)) {
            throw new BadRequestHttpException('ownerId is required');
        }

        // Déléguer à CreateFileService (validation, stockage, persistance)
        $file = $this->createFileService->createFromUpload(
            $uploadedFile,
            $ownerId,
            $request->request->get('folderId'),
            $request->request->get('newFolderName'),
            $request->request->get('relativePath'),
        );

        $batch = $this->resolveBatch($request, $ownerId);
        if ($batch !== null) {
            $file->setBatch($batch);
            $this->em->flush();
        }

        // Routage EXCLUSIF du traitement média (supports() reconnaît aussi les RAW
        // envoyés en application/octet-stream, via l'extension) :
        // - lot "deferred" (lourd) → worker Messenger seul, l'utilisateur n'attend pas ;
        // - sinon (immediate, ou pas de lot) → traitement juste après la réponse
        //   HTTP (kernel.terminate), sans mobiliser la file.
        // Un seul des deux chemins est emprunté : fini le no-op systématique du
        // worker qui refaisait le travail déjà fait à kernel.terminate.
        if ($this->mediaProcessor->supports($file->getMimeType(), $file->getOriginalName())) {
            if ($batch !== null && $batch->isDeferred()) {
                $this->bus->dispatch(new MediaProcessMessage((string) $file->getId()));
            } else {
                $this->pendingMediaProcessingCollector->add((string) $file->getId());
            }
        }

        $output = $this->provider->toOutput($file);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Résout le lot d'upload référencé par la requête, s'il existe.
     *
     * `batchId` est optionnel (uploads hors multi-fichiers, clients anciens) :
     * son absence retourne null → traitement immédiat. On vérifie que le lot
     * appartient bien à l'uploadeur pour qu'un batchId d'autrui ne puisse pas
     * détourner le routage d'un fichier.
     */
    private function resolveBatch(Request $request, string $ownerId): ?UploadBatch
    {
        $batchId = $request->request->get('batchId');
        if (empty($batchId)) {
            return null;
        }

        try {
            $batch = $this->uploadBatchRepository->findById(Uuid::fromString((string) $batchId));
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($batch === null || (string) $batch->getOwner()->getId() !== $ownerId) {
            return null;
        }

        return $batch;
    }
}

