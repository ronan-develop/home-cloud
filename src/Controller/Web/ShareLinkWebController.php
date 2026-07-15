<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Share;
use App\Entity\User;
use App\Exception\ResourceNotPubliclyShareableException;
use App\Security\ShareLinkFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Création d'un lien de partage public depuis ShareModal.
 *
 * Distinct de ShareWebController::create() (partage entre comptes) :
 * ici aucun invité n'est requis, ShareLinkFactory applique le verrou
 * de visibilité (private → 403) et l'expiration obligatoire.
 */
#[IsGranted('ROLE_USER')]
final class ShareLinkWebController extends AbstractController
{
    private const VALID_TYPES = [Share::RESOURCE_FILE, Share::RESOURCE_FOLDER, Share::RESOURCE_ALBUM];

    public function __construct(
        private readonly ShareLinkFactory $shareLinkFactory,
    ) {}

    #[Route('/share-link-create', name: 'app_share_link_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-link-create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $resourceType = (string) $request->request->get('resourceType', '');
        $resourceId   = (string) $request->request->get('resourceId', '');
        $redirectUrl  = $this->redirectUrlFor($resourceType, $resourceId);

        if (!in_array($resourceType, self::VALID_TYPES, true)) {
            $this->addFlash('error', 'Type de ressource invalide.');

            return $this->redirect($redirectUrl);
        }

        /** @var User $owner */
        $owner = $this->getUser();

        $duration = (string) $request->request->get('duration', '7d');

        try {
            $created = $this->shareLinkFactory->create(
                $owner,
                $resourceType,
                Uuid::fromString($resourceId),
                $duration,
            );
        } catch (ResourceNotPubliclyShareableException) {
            // Erreur légitime côté owner (ressource pas encore en link_allowed) :
            // redirection avec explication, pas une page d'exception brute.
            $this->addFlash('error', 'Cette ressource est privée : basculez-la en partage par lien avant de créer un lien.');

            return $this->redirect($redirectUrl);
        }
        // Non-owner ou ressource introuvable restent des 403/404 francs
        // (cf. ShareWebController::create, même comportement) : ce ne sont
        // pas des erreurs qu'un propriétaire légitime peut rencontrer.

        $publicUrl = $this->generateUrl('app_public_share', [
            'selector' => $created->link->getSelector(),
            'token'    => $created->plainToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->addFlash('success', sprintf('Lien de partage créé : %s', $publicUrl));

        return $this->redirect($redirectUrl);
    }

    private function redirectUrlFor(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            Share::RESOURCE_ALBUM  => '/albums/' . $resourceId,
            Share::RESOURCE_FOLDER => '/explorer?folder=' . $resourceId,
            default => '/explorer',
        };
    }
}
