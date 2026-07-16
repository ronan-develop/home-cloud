<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Couleur accent centralisée pour les templates email HTML (tables + CSS
 * inline, aucune variable CSS possible côté client mail). Un seul point de
 * changement aujourd'hui ; deviendra personnalisable par utilisateur plus
 * tard sans toucher aux templates.
 */
final class EmailBranding
{
    public const ACCENT_COLOR = '#A34B4B';
}
