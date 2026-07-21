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
use App\Message\ShareNotificationMessage;
use App\Security\OwnershipChecker;
use App\Security\ResourceLocator;
use App\Service\GuestAccountCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private readonly GuestAccountCreator $guestAccountCreator,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('/partages', name: 'app_shares')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // findByUser retourne owner OU guest, mais HomeCloud est mono-owner
        // par instance : l'utilisateur connecté ici est toujours l'owner, il
        // n'y a donc jamais de partage "reçu" à distinguer (cf. GuestRestrictionChecker,
        // les invités n'ont pas accès à cette page).
        $outgoing = array_map(
            fn ($share) => [
                'share'        => $share,
                'resourceName' => $this->resolveResourceName($share),
            ],
            $this->shareRepository->findByUser($user, limit: 100),
        );

        $shareLinks = array_map(
            fn ($link) => [
                'link'          => $link,
                'resourceName'  => $this->resolveResourceNameForLink($link),
                'thumbnailUrl'  => $this->resolveThumbnailUrlForLink($link),
            ],
            $this->shareLinkRepository->findByOwner($user, limit: 100),
        );

        return $this->render('web/shares.html.twig', [
            'outgoing'   => $outgoing,
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

        $emails = array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', (string) $request->request->get('guestEmail', '')),
        ), fn (string $email) => $email !== '')));

        if ($emails === []) {
            $this->addFlash('error', 'Veuillez saisir au moins un email.');

            return $this->redirect($redirectUrl);
        }

        /** @var User $owner */
        $owner = $this->getUser();

        try {
            $resource = $this->resourceLocator->locate($resourceType, \Symfony\Component\Uid\Uuid::fromString($resourceId));
            $this->ownershipChecker->denyUnlessOwner($resource);
        } catch (NotFoundHttpException) {
            $this->addFlash('error', 'Ressource introuvable.');

            return $this->redirect($redirectUrl);
        }

        $shared = [];
        $failed = [];
        $selfShareAttempted = false;

        foreach ($emails as $email) {
            $guest = $this->userRepository->findOneBy(['email' => $email]);
            $isNewGuestAccount = false;

            if ($guest === null) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $failed[] = $email . ' (email invalide)';
                    continue;
                }

                // Email inconnu mais valide : plutôt que d'échouer, on crée
                // un compte invité (sans mot de passe utilisable, cf.
                // GuestAccountCreator) et on partage avec ce nouveau compte.
                $guest = $this->guestAccountCreator->create($email, $owner);
                $isNewGuestAccount = true;
            }

            if ($guest->getId()->equals($owner->getId())) {
                $selfShareAttempted = true;
                continue;
            }

            $share = new Share($owner, $guest, $resourceType, \Symfony\Component\Uid\Uuid::fromString($resourceId), $permission);
            $this->em->persist($share);

            try {
                $this->em->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $this->em->detach($share);
                $failed[] = $email . ' (partage déjà existant)';
                continue;
            }

            // Un compte tout juste créé a déjà reçu son email d'activation
            // (GuestAccountCreator) — ne pas doubler la notification.
            // Envoi asynchrone (transport async) : ne bloque plus la réponse
            // le temps du SMTP, × nombre d'invités (#270).
            if (!$isNewGuestAccount) {
                $this->bus->dispatch(new ShareNotificationMessage(
                    (string) $share->getId(),
                    $this->resolveResourceName($share),
                ));
            }

            $shared[] = $email;
        }

        if ($shared !== []) {
            $this->addFlash('success', sprintf('Ressource partagée avec %s.', implode(', ', $shared)));
        }

        if ($failed !== []) {
            $this->addFlash('error', sprintf(
                'Échec du partage pour : %s.',
                implode(', ', $failed),
            ));
        }

        if ($selfShareAttempted && $shared === [] && $failed === []) {
            $this->addFlash('error', 'Vous ne pouvez pas partager une ressource avec vous-même.');
        }

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

    #[Route('/share-link-reactivate', name: 'app_share_link_reactivate', methods: ['POST'])]
    public function reactivateLink(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-link-reactivate', (string) $request->request->get('_token'))) {
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

        $link->reactivate();
        $this->em->flush();

        $this->addFlash('success', 'Lien de partage réactivé.');

        return $this->redirect('/partages');
    }

    #[Route('/share-revoke', name: 'app_share_revoke', methods: ['POST'])]
    public function revoke(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-revoke', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $shareId = (string) $request->request->get('shareId', '');
        $share = $this->shareRepository->find(Uuid::fromString($shareId))
            ?? throw $this->createNotFoundException('Partage introuvable.');

        $this->ownershipChecker->denyUnlessOwner($share);

        $share->revoke();
        $this->em->flush();

        $this->addFlash('success', 'Partage révoqué.');

        return $this->redirect('/partages');
    }

    #[Route('/share-reactivate', name: 'app_share_reactivate', methods: ['POST'])]
    public function reactivate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('share-reactivate', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $shareId = (string) $request->request->get('shareId', '');
        $share = $this->shareRepository->find(Uuid::fromString($shareId))
            ?? throw $this->createNotFoundException('Partage introuvable.');

        $this->ownershipChecker->denyUnlessOwner($share);

        $share->reactivate();
        $this->em->flush();

        $this->addFlash('success', 'Partage réactivé.');

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

    /**
     * Vignette du premier média AYANT UN THUMBNAIL pour un lien pointant vers
     * un album — simple aperçu visuel dans la liste, pas une vraie
     * visionneuse. Réutilise app_media_thumbnail (authentifiée) : c'est le
     * owner connecté qui consulte /partages, pas un visiteur anonyme.
     *
     * Le premier média PAR POSITION n'a pas toujours de thumbnail (traitement
     * async pas terminé, échec de génération...) : prendre systématiquement
     * ->first() affichait une vignette vide alors qu'un autre média de
     * l'album en avait une — bug capturé en usage réel.
     */
    private function resolveThumbnailUrlForLink(\App\Entity\ShareLink $link): ?string
    {
        try {
            $resource = $this->resourceLocator->locate($link->getResourceType(), $link->getResourceId());
        } catch (NotFoundHttpException) {
            return null;
        }

        if (!$resource instanceof Album) {
            return null;
        }

        foreach ($resource->getMedias() as $media) {
            if ($media->getThumbnailPath() !== null) {
                return $this->generateUrl('app_media_thumbnail', ['id' => $media->getId()]);
            }
        }

        return null;
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
