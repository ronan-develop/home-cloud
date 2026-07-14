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
 * expiresAt : obligatoire côté modèle (ShareLink ne l'accepte jamais null),
 * défaut 7 jours si absent, ramené à 30 jours si demandé au-delà.
 */
final readonly class ShareLinkFactory
{
    private const DEFAULT_EXPIRATION_DAYS = 7;
    private const MAX_EXPIRATION_DAYS = 30;

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
        ?\DateTimeImmutable $requestedExpiresAt = null,
    ): CreatedShareLink {
        $resource = $this->resourceLocator->locate($resourceType, $resourceId);

        $this->ownershipChecker->denyUnlessOwner($resource);
        $this->visibilityChecker->denyUnlessPubliclyShareable($resource);

        $expiresAt = $this->clampExpiration($requestedExpiresAt);

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

    private function clampExpiration(?\DateTimeImmutable $requested): \DateTimeImmutable
    {
        $max = new \DateTimeImmutable(sprintf('+%d days', self::MAX_EXPIRATION_DAYS));

        if ($requested === null) {
            return new \DateTimeImmutable(sprintf('+%d days', self::DEFAULT_EXPIRATION_DAYS));
        }

        return $requested > $max ? $max : $requested;
    }
}
