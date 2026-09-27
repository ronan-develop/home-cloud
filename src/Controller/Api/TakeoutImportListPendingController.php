<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\TakeoutImportRepository;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * GET /api/v1/takeout-imports/pending-list — liste tous les imports
 * "pending" de l'utilisateur courant (#481). Distinct de
 * TakeoutImportFindPendingController (singulier, le plus récent) : plusieurs
 * imports pending simultanés restent possibles (deux tentatives depuis deux
 * appareils/navigateurs différents), affichés sur la page avec reprise/
 * abandon explicites par import.
 */
#[AsController]
final class TakeoutImportListPendingController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly TakeoutImportProvider $provider,
        private readonly SerializerInterface $serializer,
    ) {}

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $imports = $this->takeoutImportRepository->findAllPendingByOwner($user);
        $outputs = array_map(fn ($import) => $this->provider->toOutput($import), $imports);

        return new JsonResponse(
            json_decode($this->serializer->serialize($outputs, 'json'), true),
            Response::HTTP_OK,
        );
    }
}
