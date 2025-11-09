<?php

namespace App\Form\Handler;

use App\Form\PhotoUploadType;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Form\Validator\UploadedFilePresenceValidator;

class PhotoUploadHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private PhotoUploader $photoUploader,
        private EntityManagerInterface $em,
        private UploadedFilePresenceValidator $filePresenceValidator
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

        if (!$form->isSubmitted() || !$form->isValid()) {
            $error = !$form->isSubmitted() ? null : 'Le formulaire contient des erreurs.';
            return $this->fail($form, $error);
        }

        // Validation présence fichier via service dédié
        $this->filePresenceValidator->validate($form, 'file');
        $file = $form->get('file')->getData();

        $photo = $this->photoUploader->uploadPhoto(
            $file,
            $user,
            $this->extractFormData($form)
        );
        $this->em->persist($photo);
        $this->em->flush();
        return [true, $form, $photo, null];
    }

    /**
     * Retourne un tableau d'échec standardisé pour handle()
     * @param \Symfony\Component\Form\FormInterface $form
     * @param string|null $error
     * @return array{0: false, 1: \Symfony\Component\Form\FormInterface, 2: null, 3: ?string}
     */
    private function fail($form, ?string $error = null): array
    {
        return [false, $form, null, $error];
    }

    /**
     * Extrait les données du formulaire d'upload photo (hors fichier)
     * @param \Symfony\Component\Form\FormInterface $form
     * @return array{title: ?string, description: ?string, isFavorite: ?bool}
     */
    private function extractFormData($form): array
    {
        return [
            'title' => $form->get('title')->getData(),
            'description' => $form->get('description')->getData(),
            'isFavorite' => $form->get('isFavorite')->getData(),
        ];
    }
}
