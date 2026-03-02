<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use App\Security\AlbumVoter;
use App\Service\AlbumService;
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
        private readonly AlbumService $albumService,
    ) {}

    #[Route('/albums', name: 'app_albums')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('web/albums.html.twig', [
            'albums' => $this->albumRepository->findByOwner($user),
        ]);
    }

    #[Route('/albums/create', name: 'app_album_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = (string) $request->request->get('name', '');

        try {
            /** @var User $user */
            $user = $this->getUser();
            $album = $this->albumService->create($name, $user);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

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
    public function delete(string $id): Response
    {
        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw $this->createNotFoundException('Album introuvable.');

        $this->denyAccessUnlessGranted(AlbumVoter::DELETE, $album);

        $name = $album->getName();
        $this->albumService->delete($album);
        $this->addFlash('success', "Album « {$name} » supprimé.");

        return $this->redirectToRoute('app_albums');
    }
}
