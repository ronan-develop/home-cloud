<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\UploadBatch;
use App\Interface\AuthenticationResolverInterface;
use App\Service\UploadRoutingDecider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déclaration d'un lot d'upload multi-fichiers.
 *
 * Le front, seul à connaître le lot entier (chaque fichier part ensuite dans sa
 * propre requête POST /api/v1/files), déclare ici le lot avant d'uploader :
 * nombre de fichiers, taille cumulée, noms. Le serveur décide alors — via
 * UploadRoutingDecider, seul juge — si le traitement média sera immédiat ou
 * déporté au worker, et retourne le batchId à joindre à chaque upload.
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
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/v1/uploads/batch', name: 'api_upload_batch_create', methods: ['POST'])]
    public function __invoke(Request $request): Response
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
}
