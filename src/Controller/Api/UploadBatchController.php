<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\UploadBatch;
use App\Interface\AuthenticationResolverInterface;
use App\Interface\UploadBatchRepositoryInterface;
use App\Service\UploadRoutingDecider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Cycle de vie d'un lot d'upload multi-fichiers.
 *
 * Le front, seul à connaître le lot entier (chaque fichier part ensuite dans sa
 * propre requête POST /api/v1/files), déclare ici le lot avant d'uploader :
 * nombre de fichiers, taille cumulée, noms. Le serveur décide alors — via
 * UploadRoutingDecider, seul juge — si le traitement média sera immédiat ou
 * déporté au worker, et retourne le batchId à joindre à chaque upload.
 *
 * Pour un lot deferred, le front interroge ensuite l'avancement (`status`) par
 * polling court afin d'afficher un toast quand tout est prêt.
 *
 * Le JS ne fait qu'informer l'utilisateur (UX) : la décision de routage n'est
 * jamais laissée au client (contournable, et ignorant du coût réel).
 */
#[AsController]
final class UploadBatchController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationResolverInterface $authResolver,
        private readonly UploadRoutingDecider $routingDecider,
        private readonly UploadBatchRepositoryInterface $uploadBatchRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/v1/uploads/batch', name: 'api_upload_batch_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $owner = $this->authResolver->requireUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $expectedCount = max(0, (int) ($data['count'] ?? 0));
        $totalSize = max(0, (int) ($data['totalSize'] ?? 0));
        $filenames = array_values(array_filter(
            (array) ($data['filenames'] ?? []),
            static fn ($name): bool => is_string($name),
        ));

        $mode = $this->routingDecider->decide($totalSize, $filenames);

        $batch = new UploadBatch($owner, $expectedCount, $totalSize, $mode);
        $this->em->persist($batch);
        $this->em->flush();

        return new JsonResponse(
            [
                'batchId' => (string) $batch->getId(),
                'mode'    => $batch->getMode(),
            ],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Avancement d'un lot, interrogé en polling court par le front (lot deferred).
     * Réponse minimale (COUNT indexé) : coût négligeable devant le traitement.
     */
    #[Route('/api/v1/uploads/{batchId}/status', name: 'api_upload_batch_status', methods: ['GET'])]
    public function status(string $batchId): Response
    {
        $owner = $this->authResolver->requireUser();

        try {
            $batch = $this->uploadBatchRepository->findById(Uuid::fromString($batchId));
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Batch not found');
        }

        if ($batch === null) {
            throw new NotFoundHttpException('Batch not found');
        }

        // Un utilisateur ne peut consulter que ses propres lots.
        if ((string) $batch->getOwner()->getId() !== (string) $owner->getId()) {
            throw new AccessDeniedHttpException('This batch belongs to another user');
        }

        return new JsonResponse([
            'status'    => $batch->getStatus(),
            'processed' => $this->uploadBatchRepository->countProcessed($batch),
            'total'     => $batch->getExpectedCount(),
        ]);
    }
}
