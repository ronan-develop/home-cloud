<?php
// src/Twig/Component/PhotoGalleryComponent.php

namespace App\Twig\Component;

use App\Entity\Photo;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('photo_gallery')]
class PhotoGalleryComponent
{
    /**
     * @var Photo[]
     */
    public array $photos = [];
}
