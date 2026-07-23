<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Interface\FileActionServiceInterface;
use App\Interface\MediaRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Renomme un média en place, sans navigation — la page appelante (galerie
 * ou album) reste affichée, le nom est mis à jour côté client via fetch().
 *
 * Contrôleur dédié (SRP) plutôt qu'une méthode de plus sur
 * MediaGalleryController, qui gère déjà listing/vue/service de fichiers.
 */
#[IsGranted('ROLE_USER')]
final class MediaRenameController extends AbstractController
{
    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly FileActionServiceInterface $fileActionService,
    ) {}

    #[Route('/gallery/{id}/rename', name: 'app_media_rename', requirements: ['id' => '[0-9a-f\-]+'], methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('media-rename', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $media = $this->mediaRepository->findById(Uuid::fromString($id));

        if ($media === null || !$media->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException('Média introuvable.');
        }

        $file = $media->getFile();
        if ($file === null) {
            // Media détaché (#246) : plus de File à renommer. Le bouton est
            // masqué côté template, mais l'endpoint doit rester sûr.
            throw new BadRequestHttpException('Impossible de renommer : le fichier source a été supprimé.');
        }

        $newName = (string) $request->request->get('name', '');
        $this->fileActionService->rename($file, $newName);

        return $this->json(['name' => $file->getOriginalName()]);
    }
}
