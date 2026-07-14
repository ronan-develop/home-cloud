<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Share;
use App\Interface\OwnershipCheckerInterface;
use App\Security\ResourceLocator;
use App\Security\VisibilityRevoker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Bascule de visibilité d'une ressource. Seule direction supportée pour
 * l'instant : repasser en `private` (le bouton d'arrêt d'urgence), qui
 * révoque en cascade tous les ShareLink actifs — cf. VisibilityRevoker.
 */
#[IsGranted('ROLE_USER')]
final class ResourceVisibilityWebController extends AbstractController
{
    private const VALID_TYPES = [Share::RESOURCE_FILE, Share::RESOURCE_FOLDER, Share::RESOURCE_ALBUM];

    public function __construct(
        private readonly ResourceLocator $resourceLocator,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly VisibilityRevoker $visibilityRevoker,
    ) {}

    #[Route('/resource-visibility-update', name: 'app_resource_visibility_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('resource-visibility-update', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $resourceType = (string) $request->request->get('resourceType', '');
        $resourceId   = (string) $request->request->get('resourceId', '');
        $visibility   = (string) $request->request->get('visibility', '');

        if (!in_array($resourceType, self::VALID_TYPES, true) || $resourceId === '') {
            throw $this->createNotFoundException('Ressource invalide.');
        }

        $uuid = Uuid::fromString($resourceId);
        $resource = $this->resourceLocator->locate($resourceType, $uuid);
        $this->ownershipChecker->denyUnlessOwner($resource);

        if ($visibility === 'private') {
            $this->visibilityRevoker->makePrivate($resource, $resourceType, $uuid);
            $this->addFlash('success', 'Ressource repassée en privé. Les liens de partage actifs ont été révoqués.');
        }

        return $this->redirect($request->headers->get('referer') ?? '/explorer');
    }
}
