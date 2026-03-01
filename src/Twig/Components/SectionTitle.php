<?php
namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'SectionTitle')]
class SectionTitle
{
    public string $text;
    public ?string $icon = null;
    // Props : texte, icône (optionnel)
}
