<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Security\BroadcastAdminChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose BroadcastAdminChecker à Twig pour conditionner l'affichage du lien
 * de navigation /admin/broadcast (#283) dans le layout partagé.
 */
final class BroadcastAdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly BroadcastAdminChecker $adminChecker,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_broadcast_admin', $this->isBroadcastAdmin(...)),
        ];
    }

    public function isBroadcastAdmin(?User $user): bool
    {
        return $user !== null && $this->adminChecker->isAdmin($user);
    }
}
