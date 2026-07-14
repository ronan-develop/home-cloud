<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\ShareLinkRepositoryInterface;
use App\Interface\ShareRepositoryInterface;
use App\Interface\UserRepositoryInterface;
use App\Security\OwnershipChecker;
use App\Security\ResourceLocator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Page web « Partages » — liste les partages sortants (créés par
 * l'utilisateur) et entrants (dont il est l'invité).
 */
#[IsGranted('ROLE_USER')]
final class ShareWebController extends AbstractController
{
    private const VALID_TYPES = [Share::RESOURCE_FILE, Share::RESOURCE_FOLDER, Share::RESOURCE_ALBUM];
    private const VALID_PERMISSIONS = [Share::PERMISSION_READ, Share::PERMISSION_WRITE];

    public function __construct(
        private readonly ShareRepositoryInterface $shareRepository,
        private readonly ShareLinkRepositoryInterface $shareLinkRepository,
        private readonly ResourceLocator $resourceLocator,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly EntityManagerInterface $em,
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

        $shareLinks = array_map(
            fn ($link) => [
                'link'         => $link,
                'resourceName' => $this->resolveResourceNameForLink($link),
            ],
            $this->shareLinkRepository->findByOwner($user, limit: 100),
        );

        return $this->render('web/shares.html.twig', [
            'outgoing'   => $outgoing,
            'incoming'   => $incoming,
            'shareLinks' => $shareLinks,
        ]);
    }

    #[Route('/share-create', name: 'app_share_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $resourceType = (string) $request->request->get('resourceType', '');
        $resourceId   = (string) $request->request->get('resourceId', '');
        $redirectUrl  = $this->redirectUrlFor($resourceType, $resourceId);

        if (!in_array($resourceType, self::VALID_TYPES, true)) {
            $this->addFlash('error', 'Type de ressource invalide.');

            return $this->redirect($redirectUrl);
        }

        $permission = (string) $request->request->get('permission', Share::PERMISSION_READ);
        if (!in_array($permission, self::VALID_PERMISSIONS, true)) {
            $permission = Share::PERMISSION_READ;
        }

        $guestEmail = trim((string) $request->request->get('guestEmail', ''));
        $guest = $guestEmail !== '' ? $this->userRepository->findOneBy(['email' => $guestEmail]) : null;

        if ($guest === null) {
            $this->addFlash('error', 'Aucun compte HomeCloud n\'est associé à cet email.');

            return $this->redirect($redirectUrl);
        }

        /** @var User $owner */
        $owner = $this->getUser();

        if ($guest->getId()->equals($owner->getId())) {
            $this->addFlash('error', 'Vous ne pouvez pas partager une ressource avec vous-même.');

            return $this->redirect($redirectUrl);
        }

        try {
            $resource = $this->resourceLocator->locate($resourceType, \Symfony\Component\Uid\Uuid::fromString($resourceId));
            $this->ownershipChecker->denyUnlessOwner($resource);
        } catch (NotFoundHttpException) {
            $this->addFlash('error', 'Ressource introuvable.');

            return $this->redirect($redirectUrl);
        }

        $share = new Share($owner, $guest, $resourceType, \Symfony\Component\Uid\Uuid::fromString($resourceId), $permission);
        $this->em->persist($share);

        try {
            $this->em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $this->addFlash('error', 'Un partage identique existe déjà pour cet utilisateur.');

            return $this->redirect($redirectUrl);
        }

        $this->addFlash('success', sprintf('Ressource partagée avec %s.', $guest->getDisplayName()));

        return $this->redirect($redirectUrl);
    }

    #[Route('/share-link-revoke', name: 'app_share_link_revoke', methods: ['POST'])]
    public function revokeLink(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-link-revoke', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $linkId = (string) $request->request->get('linkId', '');
        $link = $this->shareLinkRepository->find(Uuid::fromString($linkId))
            ?? throw $this->createNotFoundException('Lien introuvable.');

        /** @var User $user */
        $user = $this->getUser();
        if (!$link->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le propriétaire de ce lien.');
        }

        $link->revoke();
        $this->em->flush();

        $this->addFlash('success', 'Lien de partage révoqué.');

        return $this->redirect('/partages');
    }

    private function resolveResourceNameForLink(\App\Entity\ShareLink $link): string
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
