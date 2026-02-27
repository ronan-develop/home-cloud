<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiResource\FileOutput;
use App\Entity\File;
use App\Message\MediaProcessMessage;
use App\Repository\UserRepository;
use App\Service\DefaultFolderService;
use App\Service\StorageService;
use App\State\FileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller dédié à l'upload de fichiers via multipart/form-data.
 *
 * Rôle : recevoir le binaire et les métadonnées en une seule requête POST,
 * puis déléguer le stockage et la persistance aux services dédiés.
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
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly StorageService $storageService,
        private readonly DefaultFolderService $defaultFolderService,
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

        $this->rejectExecutable($uploadedFile);

        $ownerId = $request->request->get('ownerId');
        if (empty($ownerId)) {
            throw new BadRequestHttpException('ownerId is required');
        }

        $owner = $this->userRepository->find($ownerId)
            ?? throw new NotFoundHttpException('User not found');

        try {
            $folder = $this->defaultFolderService->resolve(
                $request->request->get('folderId'),
                $request->request->get('newFolderName'),
                $owner,
            );
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        // Récupérer les métadonnées AVANT store() qui déplace le fichier
        // Stripper les caractères de contrôle du nom (null bytes, newlines…) — défense XSS côté client
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $uploadedFile->getClientOriginalName()) ?: 'unnamed';
        $mimeType = $uploadedFile->getClientMimeType() ?? $uploadedFile->getMimeType() ?? 'application/octet-stream';
        $size = $uploadedFile->getSize();

        $path = $this->storageService->store($uploadedFile);

        $file = new File($originalName, $mimeType, $size, $path, $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        // Dispatch async si c'est un média (image/* ou video/*)
        if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/')) {
            $this->bus->dispatch(new MediaProcessMessage((string) $file->getId()));
        }

        $output = $this->provider->toOutput($file);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Rejette les fichiers exécutables (binaires natifs + scripts shell/batch).
     * Tous les autres formats sont acceptés sans restriction de taille.
     *
     * Extensions bloquées : exécutables OS communs (Windows, Linux, macOS, scripts).
     * Le MIME type n'est pas fiable (spoofable) → vérification par extension uniquement.
     */
    private function rejectExecutable(UploadedFile $file): void
    {
        $blockedExtensions = [
            // PHP (exécution côté serveur — RCE si le webserver interprète var/)
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
            // Windows
            'exe', 'msi', 'com', 'bat', 'cmd', 'ps1', 'psm1', 'psd1', 'scr', 'pif', 'vbs', 'vbe', 'wsf', 'wsh', 'gadget', 'msc', 'msp', 'mst',
            // Linux / Unix
            'sh', 'bash', 'zsh', 'fish', 'ksh', 'csh', 'run', 'bin', 'elf', 'appimage', 'deb', 'rpm',
            // macOS
            'dmg', 'pkg', 'app',
            // JVM / cross-platform
            'jar', 'jnlp',
            // Autres langages serveur
            'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'cfm',
        ];

        $ext = strtolower($file->getClientOriginalExtension() ?? '');

        if (in_array($ext, $blockedExtensions, true)) {
            throw new BadRequestHttpException(
                sprintf('File type ".%s" is not allowed (executables are forbidden).', $ext)
            );
        }
    }
}
