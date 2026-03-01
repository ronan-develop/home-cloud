<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'FlashMessage')]
class FlashMessage
{
    public string $type = 'info';
    public string $message;
    public ?string $icon = null;
    // Props : type, message, icône (optionnel)
}
