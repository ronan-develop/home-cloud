<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Dto\DirectMessageInput;
use App\Entity\DirectMessage;
use App\Entity\User;
use App\Form\DirectMessageFormType;
use App\Security\AdminVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface admin d'envoi d'un message ciblé 1-à-1 à un utilisateur précis
 * (#373) — distinct du broadcast (#283), pas de dépendance sur le futur
 * espace admin (#374-#377).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class DirectMessageAdminWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/admin/direct-messages', name: 'app_direct_message_admin', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        /** @var User $sender */
        $sender = $this->getUser();

        $input = new DirectMessageInput();
        $form = $this->createForm(DirectMessageFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = new DirectMessage($sender, $input->recipient, $input->subject, $input->body);
            $this->em->persist($message);
            $this->em->flush();

            return $this->redirectToRoute('app_direct_message_admin');
        }

        return $this->render('web/direct_message_admin.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
