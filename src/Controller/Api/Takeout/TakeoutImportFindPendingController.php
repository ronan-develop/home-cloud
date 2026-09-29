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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * GET /api/v1/takeout-imports/pending — retrouve l'import "pending" le plus
 * récent de l'utilisateur courant (#481). Appelé par le front avant de créer
 * un nouvel import : si l'utilisateur a fermé l'onglet en cours d'upload,
 * cet import existant doit être repris plutôt que dupliqué (chaque nouvel
 * import laisse les chunks déjà envoyés orphelins côté serveur).
 */
#[AsController]
final class TakeoutImportFindPendingController extends AbstractController
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

        $import = $this->takeoutImportRepository->findLatestPendingByOwner($user);
        if ($import === null) {
            throw new NotFoundHttpException();
        }

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_OK,
        );
    }
}
