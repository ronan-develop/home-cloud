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
 * Page web « Partages » — liste les partages sortants (créés par
 * l'utilisateur) et entrants (dont il est l'invité).
 */
#[IsGranted('ROLE_USER')]
final class ShareWebController extends AbstractController
{
    public function __construct(
        private readonly ShareRepositoryInterface $shareRepository,
        private readonly ResourceLocator $resourceLocator,
    ) {}

    #[Route('/partages', name: 'app_shares')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $shares = $this->shareRepository->findByUser($user, limit: 100);

        $outgoing = [];
        $incoming = [];

        foreach ($shares as $share) {
            $row = [
                'share'        => $share,
                'resourceName' => $this->resolveResourceName($share),
            ];

            if ($share->getOwner()->getId()->equals($user->getId())) {
                $outgoing[] = $row;
            } else {
                $incoming[] = $row;
            }
        }

        return $this->render('web/shares.html.twig', [
            'outgoing' => $outgoing,
            'incoming' => $incoming,
        ]);
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
