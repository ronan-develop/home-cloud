<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Share;

/**
 * Contrat d'envoi de la notification de partage (DIP : mockable en test,
 * appelé aussi bien en synchrone qu'via le handler asynchrone).
 */
interface ShareNotificationMailerInterface
{
    public function notify(Share $share, string $resourceName): void;
}
