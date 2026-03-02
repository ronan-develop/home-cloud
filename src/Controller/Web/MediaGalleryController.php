<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\MediaRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Galerie médias — affiche les photos et vidéos de l'utilisateur.
 * Supporte le filtrage par type (?type=photo|video).
 */
#[IsGranted('ROLE_USER')]
final class MediaGalleryController extends AbstractController
{
    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
    ) {}

    #[Route('/gallery', name: 'app_gallery')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $type = $request->query->get('type');

        $medias = $this->mediaRepository->findByOwner($user, $type ?: null);

        return $this->render('web/gallery.html.twig', [
            'medias' => $medias,
            'type'   => $type,
        ]);
    }

    #[Route('/gallery/{id}', name: 'app_media_view', requirements: ['id' => '[0-9a-f\-]+'])]
    public function view(string $id): Response
    {
        $media = $this->mediaRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($media === null || !$media->getFile()->getOwner()->getId()->equals($this->getUser()->getId())) {
            throw $this->createNotFoundException('Média introuvable.');
        }

        return $this->render('web/gallery.html.twig', [
            'medias' => [$media],
            'type'   => null,
        ]);
    }
}
