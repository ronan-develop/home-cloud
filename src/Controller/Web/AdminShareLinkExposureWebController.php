<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\ShareLink;
use App\Repository\ShareLinkRepository;
use App\Security\AdminVoter;
use App\Security\ResourceLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Surface d'exposition des liens de partage publics dans l'espace admin
 * (#387) — vue en lecture seule des ShareLink actifs de l'instance, mise en
 * avant de ceux créés il y a plus de 30 jours (candidats à une révocation
 * manuelle par leur owner ; pas d'action de révocation depuis cette vue).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminShareLinkExposureWebController extends AbstractController
{
    private const OLD_THRESHOLD_DAYS = 30;

    public function __construct(
        private readonly ShareLinkRepository $shareLinkRepository,
        private readonly ResourceLocator $resourceLocator,
    ) {}

    #[Route('/admin/share-link-exposure', name: 'app_admin_share_link_exposure', methods: ['GET'])]
    public function __invoke(): Response
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::OLD_THRESHOLD_DAYS));

        $rows = array_map(fn (ShareLink $link) => [
            'resourceName' => $this->resolveResourceName($link),
            'ownerEmail'   => $link->getOwner()->getEmail(),
            'createdAt'    => $link->getCreatedAt(),
            'expiresAt'    => $link->getExpiresAt(),
            'isOld'        => $link->getCreatedAt() < $threshold,
        ], $this->shareLinkRepository->findActiveOrderedByCreatedAt());

        return $this->render('admin/share_link_exposure.html.twig', ['rows' => $rows]);
    }

    private function resolveResourceName(ShareLink $link): string
    {
        try {
            $resource = $this->resourceLocator->locate($link->getResourceType(), $link->getResourceId());
        } catch (NotFoundHttpException) {
            return 'Ressource supprimée';
        }

        return match (true) {
            $resource instanceof File   => $resource->getOriginalName(),
            $resource instanceof Folder => $resource->getName(),
            $resource instanceof Album  => $resource->getName(),
        };
    }
}
