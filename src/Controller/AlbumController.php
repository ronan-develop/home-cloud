<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Photo;
use App\Uploader\UploaderFactory;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/albums', name: 'album_')]
class AlbumController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $albums = $albumRepository->findBy(
            ['owner' => $this->getUser()],
            ['createdAt' => 'DESC']
        );
        return $this->render('albums/index.html.twig', [
            'albums' => $albums,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UploaderFactory $uploaderFactory, #[Autowire(service: 'state_machine.album_creation')] WorkflowInterface $albumCreationStateMachine): Response
    {
        $session = $request->getSession();
        // Réinitialiser le wizard uniquement au premier accès GET (pas si déjà en cours)
        if ($request->isMethod('GET') && !$session->has('album_wizard')) {
            $session->remove('album_wizard');
        }
        $album = $session->get('album_wizard') ?: new Album();

        // Initialiser l'état au premier appel si nécessaire
        $marking = $albumCreationStateMachine->getMarking($album);
        if (empty($marking->getPlaces())) {
            $album->setMarking('titre');
        }

        $currentPlace = $albumCreationStateMachine->getMarking($album)->getPlaces();
        $currentStep = array_key_first($currentPlace);

        // ÉTAPE 1 : titre
        if ($currentStep === 'titre') {

            if ($request->isMethod('POST')) {
                $title = $request->request->get('album_title');
                if ($title) {
                    $album->setName($title);
                    $albumCreationStateMachine->apply($album, 'titre_to_photos');
                    $session->set('album_wizard', $album);
                    return $this->redirectToRoute('album_new');
                }
            }
            return $this->render('albums/new.html.twig', [
                'step' => 'titre',
                'album' => $album,
            ]);
        }

        // ÉTAPE 2 : photos
        if ($currentStep === 'photos') {
            $stored = $session->get('album_wizard');
            if ($stored instanceof Album) {
                $album = $stored;
            }
            if ($request->isMethod('POST')) {
                // Exemple : récupération des fichiers uploadés (clé 'photo_files[]')
                $files = $request->files->all('photo_files');
                $debugPhotos = [];
                if (is_array($files) && count($files) > 0) {
                    $album->getPhotos()->clear();
                    foreach ($files as $file) {
                        if ($file && $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                            try {
                                $uploader = $uploaderFactory->getUploader($file);
                                $photo = $uploader->upload($file, [
                                    'user' => $this->getUser(),
                                    // Ajoute d'autres contextes métier si besoin
                                ]);
                                $album->addPhoto($photo);
                                $debugPhotos[] = [
                                    'id' => $photo->getId(),
                                    'filename' => $photo->getFilename(),
                                    'uploader' => get_class($uploader),
                                ];
                            } catch (\Throwable $e) {
                                $debugPhotos[] = ['error' => $e->getMessage()];
                            }
                        }
                    }
                    $albumCreationStateMachine->apply($album, 'photos_to_description');
                    $session->set('album_wizard', $album);
                    // Dump détaillé pour debug
                    dd([
                        'album_photos' => $debugPhotos,
                        'album_owner' => $album->getOwner() ? [
                            'id' => $album->getOwner()->getId(),
                            'is_managed' => $em->contains($album->getOwner()),
                        ] : null,
                        'album' => $album,
                    ]);
                    return $this->redirectToRoute('album_new');
                }
            }
            // Passe l'URL API à la vue pour le JS
            $apiUrl = $this->generateUrl('api_photos_lazy');
            return $this->render('albums/new.html.twig', [
                'step' => 'photos',
                'album' => $album,
                'api_photos_url' => $apiUrl,
            ]);
        }

        // ÉTAPE 3 : description
        if ($currentStep === 'description') {
            if ($request->isMethod('POST')) {
                $desc = $request->request->get('album_description');
                if ($desc) {
                    $album->setDescription($desc);
                    $albumCreationStateMachine->apply($album, 'description_to_confirmation');
                    $session->set('album_wizard', $album);
                    return $this->redirectToRoute('album_new');
                }
            }
            return $this->render('albums/new.html.twig', [
                'step' => 'description',
                'album' => $album,
            ]);
        }

        // ÉTAPE 4 : confirmation
        if ($currentStep === 'confirmation') {
            if ($request->isMethod('POST')) {
                if ($request->request->has('back_to_photos')) {
                    // Retour à l'étape précédente
                    $albumCreationStateMachine->apply($album, 'retour_photos');
                    $session->set('album_wizard', $album);
                    return $this->redirectToRoute('album_new');
                }
                // Réhydratation des photos et users AVANT persist/flush
                $photoRepo = $em->getRepository(Photo::class);
                $photos = [];
                foreach ($album->getPhotos() as $photo) {
                    $photos[] = $photo;
                }
                // On retire chaque photo de l'album
                foreach ($photos as $photo) {
                    $album->removePhoto($photo);
                }
                // On réhydrate avec les entités managées
                foreach ($photos as $photo) {
                    $freshPhoto = $photoRepo->find($photo->getId());
                    if ($freshPhoto) {
                        // On s'assure que le user est bien l'utilisateur courant (optionnel, sécurité)
                        if ($freshPhoto->getUser() && $freshPhoto->getUser()->getId() !== $this->getUser()->getId()) {
                            // Optionnel : lever une exception ou ignorer
                            continue;
                        }
                        $album->addPhoto($freshPhoto);
                    }
                }
                $album->setOwner($this->getUser());
                $album->setCreatedAt(new \DateTimeImmutable());
                $em->persist($album);
                $em->flush();
                $session->remove('album_wizard');
                $this->addFlash('success', 'Album créé avec succès.');
                return $this->redirectToRoute('album_index');
            }
            return $this->render('albums/new.html.twig', [
                'step' => 'confirmation',
                'album' => $album,
            ]);
        }

        // Sécurité : si étape inconnue, reset
        $session->remove('album_wizard');
        return $this->redirectToRoute('album_new');
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Album $album, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $album);
        $form = $this->createForm(\App\Form\AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $album->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Album modifié avec succès.');
            return $this->redirectToRoute('album_index');
        }

        return $this->render('albums/edit.html.twig', [
            'form' => $form->createView(),
            'album' => $album,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Album $album, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $album);
        if ($this->isCsrfTokenValid('delete_album_' . $album->getId(), $request->request->get('_token'))) {
            $em->remove($album);
            $em->flush();
            $this->addFlash('success', 'Album supprimé.');
        }
        return $this->redirectToRoute('album_index');
    }
}
