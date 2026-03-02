<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Gestion des albums web — liste, création, détail, suppression.
 */
#[IsGranted('ROLE_USER')]
final class AlbumWebController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/albums', name: 'app_albums')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $albums = $this->albumRepository->findByOwner($user);

        return $this->render('web/albums.html.twig', ['albums' => $albums]);
    }

    #[Route('/albums/create', name: 'app_album_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            throw new BadRequestHttpException('Le nom de l\'album est obligatoire.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $album = new Album($name, $user);
        $this->em->persist($album);
        $this->em->flush();

        return $this->redirectToRoute('app_album_detail', ['id' => $album->getId()->toRfc4122()]);
    }

    #[Route('/albums/{id}', name: 'app_album_detail', requirements: ['id' => '[0-9a-f\-]+'])]
    public function detail(string $id): Response
    {
        $album = $this->albumRepository->find(Uuid::fromString($id));

        if ($album === null) {
            throw $this->createNotFoundException('Album introuvable.');
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$album->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Cet album ne vous appartient pas.');
        }

        return $this->render('web/album_detail.html.twig', ['album' => $album]);
    }

    #[Route('/albums/{id}/delete', name: 'app_album_delete', methods: ['POST'], requirements: ['id' => '[0-9a-f\-]+'])]
    public function delete(string $id): Response
    {
        $album = $this->albumRepository->find(Uuid::fromString($id));

        if ($album === null) {
            throw $this->createNotFoundException('Album introuvable.');
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$album->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cet album.');
        }

        $this->em->remove($album);
        $this->em->flush();

        $this->addFlash('success', "Album « {$album->getName()} » supprimé.");

        return $this->redirectToRoute('app_albums');
    }
}
