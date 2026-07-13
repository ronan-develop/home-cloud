<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Interface\MediaDeletionServiceInterface;
use App\Interface\MediaRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Supprime définitivement une sélection de médias depuis la galerie
 * (fichier + thumbnail + entité pour chacun).
 *
 * Contrôleur dédié (SRP), s'appuie sur MediaDeletionService comme
 * MediaDeleteController — la logique de suppression individuelle n'est
 * pas dupliquée, seule l'itération sur la sélection est ajoutée ici.
 * Ignore silencieusement les médias inexistants ou n'appartenant pas à
 * l'utilisateur (pas d'erreur bloquante sur une suppression multiple).
 */
#[IsGranted('ROLE_USER')]
final class MediaBulkDeleteController extends AbstractController
{
    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly MediaDeletionServiceInterface $mediaDeletionService,
    ) {}

    #[Route('/gallery/bulk-delete', name: 'app_media_bulk_delete', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $mediaIds = $request->request->all('mediaIds');
        $user = $this->getUser();
        $deletedCount = 0;

        foreach ($mediaIds as $mediaId) {
            $media = $this->mediaRepository->findById(Uuid::fromString($mediaId));

            if ($media === null || !$media->isOwnedBy($user)) {
                continue;
            }

            $this->mediaDeletionService->delete($media);
            ++$deletedCount;
        }

        return $this->json(['deletedCount' => $deletedCount]);
    }
}
