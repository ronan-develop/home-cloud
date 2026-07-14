<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Interface\ShareLinkAccessCheckerInterface;
use App\Security\ResourceLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Consultation d'une ressource via un lien de partage public — sans compte,
 * sans session. Le secret est entièrement porté par (selector, token) dans
 * l'URL ; aucune vérification ne repose sur l'utilisateur connecté.
 *
 * Volontairement : token faux, lien expiré ou révoqué renvoient tous 404
 * (jamais 403), pour ne pas confirmer à un attaquant qu'un selector existe.
 */
final class PublicShareController extends AbstractController
{
    public function __construct(
        private readonly ShareLinkAccessCheckerInterface $shareLinkAccessChecker,
        private readonly ResourceLocator $resourceLocator,
    ) {}

    #[Route('/p/{selector}/{token}', name: 'app_public_share', methods: ['GET'])]
    public function show(string $selector, string $token): Response
    {
        $link = $this->shareLinkAccessChecker->resolve($selector, $token);

        if ($link === null) {
            throw new NotFoundHttpException();
        }

        // ResourceLocator::locate() lève déjà NotFoundHttpException si la
        // ressource a été supprimée depuis la création du lien — pas de
        // traitement supplémentaire nécessaire, la 404 se propage telle quelle.
        $resource = $this->resourceLocator->locate($link->getResourceType(), $link->getResourceId());

        $response = $this->render('web/public_share.html.twig', [
            'resource'     => $resource,
            'resourceType' => $link->getResourceType(),
            'resourceName' => $this->resourceName($resource),
            'link'         => $link,
        ]);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function resourceName(File|Folder|Album $resource): string
    {
        return match (true) {
            $resource instanceof File   => $resource->getOriginalName(),
            $resource instanceof Folder => $resource->getName(),
            $resource instanceof Album  => $resource->getName(),
        };
    }
}
