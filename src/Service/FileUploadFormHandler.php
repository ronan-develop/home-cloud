<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadFormHandler
{
    public function getUploadedFile(FormInterface $form): UploadedFile
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new \DomainException('Le formulaire n\'est pas soumis ou invalide.');
        }
        $uploadedFile = $form->get('file')->getData();
        if (!$uploadedFile) {
            throw new \DomainException('Aucun fichier uploadé.');
        }
        return $uploadedFile;
    }
}
