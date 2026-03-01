<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'FoldersGrid')]
class FoldersGrid
{
    /**
     * @var array<int, object>
     */
    public array $items = [];
    // Props : liste de fichiers/dossiers, options d’affichage (à étendre si besoin)
}
