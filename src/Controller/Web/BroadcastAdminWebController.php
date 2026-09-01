<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Dto\BroadcastMessageInput;
use App\Form\BroadcastMessageFormType;
use App\Interface\BroadcastOrchestratorInterface;
use App\Security\AdminVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface admin de diffusion d'un message (maintenance/indisponibilité,
 * #283) à tous les utilisateurs de toutes les instances, ou une instance
 * ciblée. Réservée à l'admin whitelisté (AdminVoter → BroadcastAdminChecker)
 * — pas de ROLE_ADMIN Symfony, cf. justification dans BroadcastAdminChecker.
 */
#[IsGranted(AdminVoter::ADMIN)]
final class BroadcastAdminWebController extends AbstractController
{
    public function __construct(
        private readonly BroadcastOrchestratorInterface $orchestrator,
    ) {}

    #[Route('/admin/broadcast', name: 'app_broadcast_admin', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $input = new BroadcastMessageInput();
        $form = $this->createForm(BroadcastMessageFormType::class, $input);
        $form->handleRequest($request);

        $results = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $results = $this->orchestrator->dispatch(
                $input->subject,
                $input->body,
                $input->targetInstance,
                $input->dryRun,
            );
        }

        return $this->render('web/broadcast_admin.html.twig', [
            'form'    => $form->createView(),
            'results' => $results,
        ]);
    }
}
