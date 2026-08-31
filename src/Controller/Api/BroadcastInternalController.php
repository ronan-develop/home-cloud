<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Interface\BroadcastInAppNotifierInterface;
use App\Interface\BroadcastMailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint interne inter-instances du broadcast admin (#283). Appelé par
 * l'instance ronan.lenouvel.me sur chaque autre instance pour y déclencher
 * l'envoi local (DB isolée par instance). Authentification par secret
 * partagé via BroadcastTokenAuthenticator, hors du firewall JWT `api`.
 */
final class BroadcastInternalController extends AbstractController
{
    public function __construct(
        private readonly BroadcastMailerInterface $broadcastMailer,
        private readonly BroadcastInAppNotifierInterface $inAppNotifier,
    ) {}

    #[Route('/internal/broadcast', name: 'internal_broadcast', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $subject = (string) ($data['subject'] ?? '');
        $body = (string) ($data['body'] ?? '');
        $dryRun = (bool) ($data['dryRun'] ?? false);

        $sent = $this->broadcastMailer->sendToAllUsers($subject, $body, $dryRun);

        if ((bool) ($data['alsoInApp'] ?? false)) {
            $this->inAppNotifier->notify($subject, $body, $dryRun);
        }

        return new JsonResponse(['sent' => $sent]);
    }
}
