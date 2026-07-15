<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ShareLink;
use App\Entity\User;
use App\Interface\OwnershipCheckerInterface;
use App\Interface\ResourceLocatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Construit et persiste un ShareLink après vérifications :
 * - ownership réel de la ressource (denyUnlessOwner)
 * - verrou de visibilité (denyUnlessPubliclyShareable) : c'est le point qui
 *   fait exister le verrou posé à l'étape 0. Sans cet appel, `visibility`
 *   ne serait qu'un champ décoratif — c'est ICI qu'il devient une garantie.
 *
 * $duration : choix explicite de l'owner à la création, pas un champ libre.
 * '1d'|'7d'|'30d' bornent une durée fixe ; 'permanent' laisse expiresAt à
 * null (cf. ShareLink — usage type livraison d'album à un client, où la
 * révocation manuelle reste le seul moyen de couper l'accès). Une valeur
 * absente ou non reconnue retombe sur le défaut 7 jours plutôt que d'échouer :
 * ce n'est pas une donnée de sécurité critique, une erreur de saisie ne doit
 * pas bloquer la création du lien.
 */
final readonly class ShareLinkFactory
{
    private const DURATIONS = [
        '1d'  => 1,
        '7d'  => 7,
        '30d' => 30,
    ];
    private const DEFAULT_DURATION = '7d';

    public function __construct(
        private ResourceLocatorInterface $resourceLocator,
        private OwnershipCheckerInterface $ownershipChecker,
        private VisibilityChecker $visibilityChecker,
        private ShareLinkTokenGenerator $tokenGenerator,
        private EntityManagerInterface $em,
    ) {}

    public function create(
        User $owner,
        string $resourceType,
        Uuid $resourceId,
        string $duration = self::DEFAULT_DURATION,
    ): CreatedShareLink {
        $resource = $this->resourceLocator->locate($resourceType, $resourceId);

        $this->ownershipChecker->denyUnlessOwner($resource);
        $this->visibilityChecker->denyUnlessPubliclyShareable($resource);

        $expiresAt = $this->resolveExpiration($duration);

        $generated = $this->tokenGenerator->generate();

        $link = new ShareLink(
            $owner,
            $resourceType,
            $resourceId,
            $generated->selector,
            $generated->hashedToken,
            $expiresAt,
        );

        $this->em->persist($link);
        $this->em->flush();

        return new CreatedShareLink($link, $generated->token);
    }

    private function resolveExpiration(string $duration): ?\DateTimeImmutable
    {
        if ($duration === 'permanent') {
            return null;
        }

        $days = self::DURATIONS[$duration] ?? self::DURATIONS[self::DEFAULT_DURATION];

        return new \DateTimeImmutable(sprintf('+%d days', $days));
    }
}
