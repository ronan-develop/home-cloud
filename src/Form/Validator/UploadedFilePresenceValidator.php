<?php

namespace App\Form\Validator;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exception\PhotoUploadException;

class UploadedFilePresenceValidator
{
    /**
     * Vérifie la présence d'un fichier uploadé dans le formulaire.
     * @param FormInterface $form
     * @param string $fieldName
     * @throws PhotoUploadException
     */
    public function validate(FormInterface $form, string $fieldName = 'file'): void
    {
        /** @var UploadedFile|null $file */
        $file = $form->get($fieldName)->getData();
        if (!$file) {
            throw new PhotoUploadException('Aucun fichier sélectionné.');
        }
    }
}
