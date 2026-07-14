<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\AlbumImportServiceInterface;
use App\Interface\AlbumRepositoryInterface;
use App\Interface\AlbumServiceInterface;
use App\Interface\MediaRepositoryInterface;
use App\Security\AlbumVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Gestion des albums web — liste, création, détail, suppression.
 * Délègue la logique métier à AlbumService et les contrôles d'accès à AlbumVoter.
 */
#[IsGranted('ROLE_USER')]
final class AlbumWebController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly AlbumServiceInterface $albumService,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly AlbumImportServiceInterface $albumImportService,
    ) {}

    #[Route('/albums', name: 'app_albums')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('web/albums.html.twig', [
            'albums' => $this->albumRepository->findByOwner($user),
            'medias' => $this->mediaRepository->findByOwner($user),
        ]);
    }

    #[Route('/albums/create', name: 'app_album_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name     = (string) $request->request->get('name', '');
        $mediaIds = $request->request->all('mediaIds');

        /** @var User $user */
        $user  = $this->getUser();

        try {
            $album = $this->albumService->create($name, $user, $mediaIds);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}/add-media', name: 'app_album_add_media', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+'])]
    public function addMedia(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-add-media', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        /** @var User $user */
        $user     = $this->getUser();
        $mediaIds = $request->request->all('mediaIds');

        $this->albumService->addMedias($album, $mediaIds, $user);

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}/medias/{mediaId}/remove', name: 'app_album_remove_media', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+', 'mediaId' => '[0-9a-f\-]+'])]
    public function removeMedia(string $id, string $mediaId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-remove-media', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        $media = $this->mediaRepository->findById(Uuid::fromString($mediaId))
            ?? throw $this->createNotFoundException('Média introuvable.');

        $this->albumService->removeMedia($album, $media);

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}/reorder', name: 'app_album_reorder', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+'])]
    public function reorder(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-reorder', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        $mediaIds = $request->request->all('mediaIds');
        $this->albumService->reorder($album, $mediaIds);

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}/import', name: 'app_album_import', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+'])]
    public function import(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-import', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        /** @var User $user */
        $user  = $this->getUser();
        $files = $request->files->all('files');

        $this->albumImportService->import($album, $files, $user);

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}', name: 'app_album_detail', requirements: ['id' => '[0-9a-f\-]+'])]
    public function detail(string $id): Response
    {
        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        return $this->render('web/album_detail.html.twig', ['album' => $album]);
    }

    #[Route('/albums/{id}/delete', name: 'app_album_delete', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('album-delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::DELETE, $album);

        $name = $album->getName();
        $this->albumService->delete($album);
        $this->addFlash('success', "Album « {$name} » supprimé.");

        return $this->redirectToRoute('app_albums');
    }
}
