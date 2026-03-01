<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class EmptyState
{
    public string $title = '';
    public string $subtitle = '';
    public ?string $icon = null;
    public ?string $actionLabel = null;
    public ?string $actionUrl = null;
}
