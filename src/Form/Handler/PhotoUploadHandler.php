<?php

namespace App\Form\Handler;

use Psr\Log\LoggerInterface;
use App\Form\PhotoUploadType;
use App\Uploader\PhotoUploader;
use App\Form\Dto\PhotoUploadData;
use App\Form\Dto\PhotoUploadResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactoryInterface;
use App\Form\Validator\UploadedFilePresenceValidator;
use Symfony\Component\Security\Core\User\UserInterface;

class PhotoUploadHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private PhotoUploader $photoUploader,
        private EntityManagerInterface $em,
        private UploadedFilePresenceValidator $filePresenceValidator,
        private LoggerInterface $logger
    ) {}

    /**
     * Traite le formulaire d'upload de photo.
     *
     * Cette méthode gère la soumission, la validation et l'upload d'une photo via le formulaire PhotoUploadType.
     * Elle retourne un objet PhotoUploadResult contenant :
     *   - bool $success : true si l'upload a réussi, false sinon
     *   - FormInterface $form : instance du formulaire (pour l'affichage ou les erreurs)
     *   - Photo|null $photo : entité Photo créée et persistée, ou null en cas d'échec
     *   - string|null $errorMessage : message d'erreur métier ou technique, null si succès
     *
     * @param Request $request La requête HTTP courante
     * @param UserInterface $user L'utilisateur courant (propriétaire de la photo)
     *
     * @return PhotoUploadResult
     */
    public function handle(Request $request, UserInterface $user): PhotoUploadResult
    {
        $form = $this->formFactory->create(PhotoUploadType::class);
        $form->handleRequest($request);
        if (!\in_array('ROLE_USER', method_exists($user, 'getRoles') ? $user->getRoles() : [], true)) {
            return new PhotoUploadResult(false, $form, null, "Vous n'avez pas le droit d'uploader des photos.");
        }

        if (!$form->isSubmitted() || !$form->isValid()) {
            $error = !$form->isSubmitted() ? null : 'Le formulaire contient des erreurs.';
            return new PhotoUploadResult(false, $form, null, $error);
        }

        try {
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
            return new PhotoUploadResult(true, $form, $photo, null);
        } catch (\InvalidArgumentException $e) {
            // Erreur métier (ex: type MIME refusé)
            return new PhotoUploadResult(false, $form, null, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Erreur critique lors de l\'upload de photo', [
                'exception' => $e,
                'user' => $user->getUserIdentifier(),
                'request_uri' => $request->getRequestUri(),
            ]);
            return new PhotoUploadResult(false, $form, null, 'Une erreur technique est survenue lors de l\'upload.');
        }
    }

    /**
     * Extrait les données du formulaire d'upload photo (hors fichier)
     * @param \Symfony\Component\Form\FormInterface $form
     * @return PhotoUploadData
     */
    private function extractFormData(FormInterface $form): PhotoUploadData
    {
        return new PhotoUploadData(
            $form->get('title')->getData(),
            $form->get('description')->getData(),
            $form->get('isFavorite')->getData()
        );
    }
}
