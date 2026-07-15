<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Levée quand un compte invité tente une action réservée aux comptes complets
 * (upload, création de dossier/album) — un invité est exclusivement
 * lecture/téléchargement, quelle que soit la permission du Share reçu.
 */
final class GuestNotAllowedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Un compte invité ne peut pas créer de contenu.');
    }
}
