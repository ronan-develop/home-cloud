<?php

namespace App\Form\Handler;

use App\Form\PhotoUploadType;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoUploadHandler
{
    private FormFactoryInterface $formFactory;
    private PhotoUploader $photoUploader;
    private EntityManagerInterface $em;

    public function __construct(FormFactoryInterface $formFactory, PhotoUploader $photoUploader, EntityManagerInterface $em)
    {
        $this->formFactory = $formFactory;
        $this->photoUploader = $photoUploader;
        $this->em = $em;
    }

    /**
     * Traite le formulaire d'upload photo et retourne [success, form, photo|null]
     */
    public function handle(Request $request, UserInterface $user): array
    {
        $form = $this->formFactory->create(PhotoUploadType::class);
        $form->handleRequest($request);
        $photo = null;
        $success = false;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();
            if ($file) {
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
                $success = true;
            }
        }
        return [$success, $form, $photo];
    }
}
