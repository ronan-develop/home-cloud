<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'SidebarFolders')]
class SidebarFolders
{
    /**
     * @var array<int, object>
     */
    public array $folders = [];
    public $currentFolder = null;
    // Props : liste de dossiers, dossier courant
}
