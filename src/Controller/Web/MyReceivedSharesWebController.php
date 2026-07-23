<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\ShareRepositoryInterface;
use App\Security\ResourceLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page web « Mes partages » — liste, pour l'utilisateur connecté, les
 * partages actifs dont il est le destinataire (guest). Cf. issue #273 :
 * un invité n'a sinon aucun moyen de retrouver ses partages reçus en dehors
 * du lien contenu dans l'email de notification.
 */
#[IsGranted('ROLE_USER')]
final class MyReceivedSharesWebController extends AbstractController
{
    public function __construct(
        private readonly ShareRepositoryInterface $shareRepository,
        private readonly ResourceLocator $resourceLocator,
    ) {}

    #[Route('/mes-partages', name: 'app_my_received_shares')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $incoming = array_map(
            fn (Share $share) => [
                'share'        => $share,
                'resourceName' => $this->resolveResourceName($share),
                'targetUrl'    => $this->redirectUrlFor($share->getResourceType(), (string) $share->getResourceId()),
            ],
            $this->shareRepository->findActiveByGuest($user),
        );

        return $this->render('web/my_received_shares.html.twig', [
            'incoming' => $incoming,
        ]);
    }

    private function redirectUrlFor(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            Share::RESOURCE_ALBUM  => '/albums/' . $resourceId,
            Share::RESOURCE_FOLDER => '/explorer?folder=' . $resourceId,
            default => '/explorer',
        };
    }

    private function resolveResourceName(Share $share): string
    {
        try {
            $resource = $this->resourceLocator->locate($share->getResourceType(), $share->getResourceId());
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
