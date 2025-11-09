<?php

namespace App\Form\Handler;

use App\Form\PhotoUploadType;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exception\PhotoUploadException;

class PhotoUploadHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private PhotoUploader $photoUploader,
        private EntityManagerInterface $em
    ) {}

    /**
     * Traite le formulaire d'upload de photo.
     * 
     * Cette méthode gère la soumission, la validation et l'upload d'une photo via le formulaire PhotoUploadType.
     * Elle retourne un tableau contenant :
     *   - bool $success : true si l'upload a réussi, false sinon
     *   - FormInterface $form : instance du formulaire (pour l'affichage ou les erreurs)
     *   - Photo|null $photo : entité Photo créée et persistée, ou null en cas d'échec
     *   - string|null $errorMessage : message d'erreur métier ou technique, null si succès
     *
     * @param Request $request La requête HTTP courante
     * @param UserInterface $user L'utilisateur courant (propriétaire de la photo)
     *
     * @return array{0: bool, 1: \Symfony\Component\Form\FormInterface, 2: ?\App\Entity\Photo, 3: ?string}
     */
    public function handle(Request $request, UserInterface $user): array
    {

        $form = $this->formFactory->create(PhotoUploadType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return [false, $form, null, null];
        }
        if (!$form->isValid()) {
            return [false, $form, null, 'Le formulaire contient des erreurs.'];
        }

        /** @var UploadedFile|null $file */
        $file = $form->get('file')->getData();
        if (!$file) {
            return [false, $form, null, 'Aucun fichier sélectionné.'];
        }

        try {
            $photo = $this->photoUploader->uploadPhoto(
                $file,
                $user,
                [
                    'title' => $form->get('title')->getData(),
                    'description' => $form->get('description')->getData(),
                    'isFavorite' => $form->get('isFavorite')->getData(),
                ]
            );
            $this->em->persist($photo);
            $this->em->flush();
            return [true, $form, $photo, null];
        } catch (PhotoUploadException $e) {
            return [false, $form, null, 'Erreur métier lors de l\'upload : ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return [false, $form, null, 'Erreur technique lors de l\'upload : ' . $e->getMessage()];
        }
    }
}
