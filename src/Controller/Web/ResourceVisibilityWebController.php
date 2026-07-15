<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Interface\OwnershipCheckerInterface;
use App\Security\ResourceLocator;
use App\Security\VisibilityRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Bascule de visibilité d'une ressource, dans les deux sens :
 * - vers `private` (bouton d'arrêt d'urgence) : révoque en cascade tous les
 *   ShareLink actifs — cf. VisibilityRevoker.
 * - vers `link_allowed` (opt-in explicite) : préalable indispensable avant
 *   que ShareLink puisse être créé sur cette ressource (VisibilityChecker
 *   refuse sinon, cf. ShareLinkFactory).
 */
#[IsGranted('ROLE_USER')]
final class ResourceVisibilityWebController extends AbstractController
{
    private const VALID_TYPES = [Share::RESOURCE_FILE, Share::RESOURCE_FOLDER, Share::RESOURCE_ALBUM];

    public function __construct(
        private readonly ResourceLocator $resourceLocator,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly VisibilityRevoker $visibilityRevoker,
        private readonly EntityManagerInterface $em,
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
        } elseif ($visibility === 'link_allowed') {
            $resource->setVisibility(match (true) {
                $resource instanceof File   => File::VISIBILITY_LINK_ALLOWED,
                $resource instanceof Folder => Folder::VISIBILITY_LINK_ALLOWED,
                $resource instanceof Album  => Album::VISIBILITY_LINK_ALLOWED,
            });
            $this->em->flush();
            $this->addFlash('success', 'Partage par lien autorisé pour cette ressource.');
        }

        return $this->redirect($this->redirectUrlFor($resourceType, $resourceId, $resource));
    }

    /**
     * Dérive l'URL de retour depuis (resourceType, resourceId) plutôt que
     * l'en-tête Referer : Referrer-Policy: no-referrer (posé pour protéger
     * les tokens de ShareLink) fait que le navigateur ne l'envoie jamais,
     * donc s'appuyer dessus renvoyait systématiquement vers /explorer.
     */
    private function redirectUrlFor(string $resourceType, string $resourceId, File|Folder|Album $resource): string
    {
        return match ($resourceType) {
            Share::RESOURCE_ALBUM  => '/albums/' . $resourceId,
            Share::RESOURCE_FOLDER => '/explorer?folder=' . $resourceId,
            // Un fichier isolé n'a pas sa propre page : revenir au dossier qui le contient.
            Share::RESOURCE_FILE   => '/explorer?folder=' . $resource->getFolder()->getId(),
            default => '/explorer',
        };
    }
}
