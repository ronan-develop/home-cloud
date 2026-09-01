<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Security\AdminVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose AdminVoter à Twig pour conditionner l'affichage du lien de
 * navigation /admin/broadcast (#283) dans le layout partagé.
 */
final class BroadcastAdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_broadcast_admin', $this->isBroadcastAdmin(...)),
        ];
    }

    public function isBroadcastAdmin(?User $user): bool
    {
        return $user !== null && $this->security->isGranted(AdminVoter::ADMIN);
    }
}
