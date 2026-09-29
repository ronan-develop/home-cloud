<?php

declare(strict_types=1);

namespace App\Controller\Api\Takeout;

use App\Entity\TakeoutImport;
use App\Repository\TakeoutImportRepository;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * POST /api/v1/takeout-imports — crée l'import (statut pending), sans
 * fichier (#466 : envoyer chaque ZIP séparément via
 * TakeoutImportFileUploadController, pour rester sous la limite de taille
 * de requête du serveur mutualisé).
 */
#[AsController]
final class TakeoutImportCreateController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly TakeoutImportProvider $provider,
        private readonly SerializerInterface $serializer,
    ) {}

    public function __invoke(): Response
    {
        $import = new TakeoutImport($this->getUser());
        $this->takeoutImportRepository->save($import);

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_CREATED,
        );
    }
}
