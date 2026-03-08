<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiResource\FileOutput;
use App\Message\MediaProcessMessage;
use App\Service\CreateFileService;
use App\State\FileProvider;
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
    ) {}

    /**
     * POST /api/v1/files — upload multipart/form-data.
     *
     * Champs form attendus :
     *   file           (fichier binaire, obligatoire)
     *   ownerId        (UUID utilisateur, obligatoire)
     *   folderId       (UUID folder existant, optionnel)
     *   newFolderName  (nom du nouveau folder à créer, optionnel)
     *
     * Priorité : folderId > newFolderName > dossier "Uploads"
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
        );

        // Dispatch async si c'est un média (image/* ou video/*)
        $mimeType = $file->getMimeType();
        if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/')) {
            $this->bus->dispatch(new MediaProcessMessage((string) $file->getId()));
        }

        $output = $this->provider->toOutput($file);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_CREATED,
        );
    }
}

