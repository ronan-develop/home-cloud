<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'ImportCard')]
class ImportCard
{
    public ?string $currentFolder = null;
    // Props : dossier courant (optionnel)
}
