<?php

declare(strict_types=1);

namespace App\Controller\Api\Takeout;

use App\Entity\User;
use App\Repository\TakeoutImportRepository;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * GET /api/v1/takeout-imports/pending-list — liste tous les imports non
 * terminaux de l'utilisateur courant : pending (#481), scheduled/extracting/
 * processing (#545). Distinct de TakeoutImportFindPendingController
 * (singulier, le plus récent, pending strict) : ici plusieurs imports actifs
 * simultanés restent possibles, affichés sur la page avec reprise (pending
 * uniquement) ou juste un état visuel pour les autres.
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

        $imports = $this->takeoutImportRepository->findAllActiveByOwner($user);
        $outputs = array_map(fn ($import) => $this->provider->toOutput($import), $imports);

        return new JsonResponse(
            json_decode($this->serializer->serialize($outputs, 'json'), true),
            Response::HTTP_OK,
        );
    }
}
