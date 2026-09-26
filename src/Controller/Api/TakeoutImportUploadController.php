<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Message\TakeoutImportMessage;
use App\Repository\TakeoutImportRepository;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller dédié à l'upload du/des ZIP d'un export Google Photos Takeout
 * (#327) — multipart/form-data, comme FileUploadController.
 *
 * Les ZIP sont stockés dans un répertoire temporaire dédié (pas
 * StorageService : ce ne sont jamais des fichiers finalisés), le
 * TakeoutImport est créé en base (statut pending) et le traitement réel est
 * délégué au worker asynchrone (TakeoutImportHandler) — la réponse HTTP
 * (202) ne bloque jamais sur l'extraction/l'import qui peut durer plusieurs
 * minutes sur un export volumineux.
 */
#[AsController]
final class TakeoutImportUploadController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly TakeoutImportProvider $provider,
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $bus,
        private readonly string $takeoutTmpDir,
    ) {}

    /**
     * POST /api/v1/takeout-imports — upload multipart/form-data.
     *
     * Champ form attendu : files[] (un ou plusieurs ZIP takeout-*.zip)
     */
    public function __invoke(Request $request): Response
    {
        $uploadedFiles = $request->files->all('files');
        if ($uploadedFiles === []) {
            throw new BadRequestHttpException('At least one ZIP file must be uploaded (multipart field: "files[]")');
        }

        $owner = $this->getUser();

        $import = new TakeoutImport($owner);
        $this->takeoutImportRepository->save($import);

        $importTmpDir = sprintf('%s/%s', $this->takeoutTmpDir, $import->getId()->toRfc4122());
        if (!is_dir($importTmpDir)) {
            mkdir($importTmpDir, 0777, true);
        }

        $zipPaths = [];
        foreach ($uploadedFiles as $index => $uploadedFile) {
            $filename = sprintf('%d-%s', $index, $uploadedFile->getClientOriginalName());
            $uploadedFile->move($importTmpDir, $filename);
            $zipPaths[] = $importTmpDir . '/' . $filename;
        }

        $this->bus->dispatch(new TakeoutImportMessage((string) $import->getId(), $zipPaths));

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_ACCEPTED,
        );
    }
}
