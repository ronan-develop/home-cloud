<?php

namespace App\Form\Dto;

use App\Entity\Photo;
use Symfony\Component\Form\FormInterface;

class PhotoUploadResult
{
    public function __construct(
        public readonly bool $success,
        public readonly FormInterface $form,
        public readonly ?Photo $photo,
        public readonly ?string $errorMessage = null
    ) {}
}
