<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'MainToolbar')]
class MainToolbar
{
    public string $placeholder = 'Rechercher...';
    /**
     * @var array<int, array{icon: string, title: string}>
     */
    public array $actions = [];
    // Props : placeholder, actions (tableau d’icônes/boutons)
}
