<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class DirectMessageInput
{
    #[Assert\NotNull(message: 'Le destinataire est obligatoire.')]
    public ?User $recipient = null;

    #[Assert\NotBlank(message: 'Le sujet est obligatoire.')]
    public string $subject = '';

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    public string $body = '';
}
