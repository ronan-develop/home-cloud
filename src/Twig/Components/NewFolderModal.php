<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class NewFolderModal
{
    /** @var array<\App\Entity\Folder> */
    public array $folders = [];
}
