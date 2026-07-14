<?php

namespace App\Twig\Components;

use App\Entity\Folder;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'ImportCard')]
class ImportCard
{
    public ?Folder $currentFolder = null;
    // Props : dossier courant (optionnel)
}
