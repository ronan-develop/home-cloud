<?php

namespace App\Form\Dto;

class PhotoUploadData
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?bool $isFavorite
    ) {}
}
