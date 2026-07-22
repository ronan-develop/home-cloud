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

    /**
     * @var array<string, \App\Entity\Media> Media indexés par id de File (RFC 4122)
     */
    public array $mediasByFileId = [];
    // Props : liste de fichiers/dossiers, options d’affichage (à étendre si besoin)
}
