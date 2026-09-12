<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\DeployImminentChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint de polling exposant l'imminence d'un déploiement (#422 étape
 * 3/3) — consulté par la popup front avec compte à rebours. Accessible à
 * tout utilisateur authentifié, pas réservé à l'admin : n'importe quel user
 * actif sur l'instance doit être averti avant une coupure.
 */
#[IsGranted('ROLE_USER')]
final class DeployStatusWebController extends AbstractController
{
    public function __construct(
        private readonly DeployImminentChecker $checker,
    ) {}

    #[Route('/deploy-status', name: 'app_deploy_status', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $eta = $this->checker->getDeploymentEta();

        if ($eta === null) {
            return new JsonResponse(['imminent' => false]);
        }

        $etaSeconds = max(0, $eta->getTimestamp() - time());

        return new JsonResponse(['imminent' => true, 'etaSeconds' => $etaSeconds]);
    }
}
