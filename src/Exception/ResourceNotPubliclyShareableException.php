<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Levée quand on tente de créer un lien public sur une ressource `private`
 * (ou dont un parent est `private` — le plus restrictif gagne).
 */
final class ResourceNotPubliclyShareableException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Cette ressource n\'est pas autorisée au partage par lien public.');
    }
}
