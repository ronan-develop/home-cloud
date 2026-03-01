<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Modal
{
    public string $title = '';
    public ?string $icon = null;
    public ?string $size = 'md'; // sm, md, lg
    public bool $open = false;
    public ?string $content = null; // contenu HTML ou texte
    public array $actions = []; // [{label, variant, action, ...}]
    public ?string $id = null;
}
