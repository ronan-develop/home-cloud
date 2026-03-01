<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Button
{
    public string $type = 'button'; // button, submit, reset
    public string $label = '';
    public ?string $icon = null;
    public ?string $variant = 'primary'; // primary, secondary, danger, etc.
    public ?string $size = 'md'; // sm, md, lg
    public ?string $href = null; // Si défini, rend un <a> sinon <button>
    public ?string $class = null;
    public ?string $form = null;
}
