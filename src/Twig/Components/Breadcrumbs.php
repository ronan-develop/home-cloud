<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Breadcrumbs')]
class Breadcrumbs
{
    /**
     * @var array<int, array{label: string, url: ?string}>
     */
    public array $segments = [];
    // Props : tableau de segments (label + url)
}
