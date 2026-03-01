<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'FolderCard')]
class FolderCard
{
    public object $item;
    // Props : objet dossier/fichier (doit avoir name, isFolder, etc.)
}
