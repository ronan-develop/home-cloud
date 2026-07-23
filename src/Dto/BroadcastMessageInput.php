<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BroadcastMessageInput
{
    #[Assert\NotBlank(message: 'Le sujet est obligatoire.')]
    public string $subject = '';

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    public string $body = '';

    /** null = toutes les instances */
    public ?string $targetInstance = null;

    public bool $dryRun = false;
}
