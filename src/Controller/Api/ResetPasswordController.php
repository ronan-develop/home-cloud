<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;


class ResetPasswordController extends AbstractController
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private \App\Interface\PasswordResetServiceInterface $passwordResetService,
    ) {}

    /**
     * Dérive l'adresse d'expédition du host de la requête (ex: alice.homecloud.fr
     * → no-reply@alice.homecloud.fr), pour rester correct sur n'importe quel
     * sous-domaine sans configuration manuelle par instance.
     */
    private function mailerFromAddress(Request $request): string
    {
        return 'no-reply@' . $request->getHost();
    }

    #[Route('/reset-password', name: 'web_reset_password', methods: ['GET'])]
    public function webResetPassword(): Response
    {
        return $this->render('reset_password/request.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'web_reset_password_confirm', methods: ['GET'])]
    public function webResetPasswordConfirm(string $token): Response
    {
        return $this->render('reset_password/confirm.html.twig', ['token' => $token]);
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function apiResetPassword(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;
        if (!$token || !$newPassword) {
            return $this->json(['error' => 'Token et nouveau mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $this->passwordResetService->resetPassword($token, $newPassword);
        } catch (\SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface $e) {
            return $this->json(['error' => 'Token invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }
        return $this->json(['message' => 'Votre mot de passe a été réinitialisé avec succès.']);
    }

    #[Route('/api/request-reset-password', name: 'api_request_reset_password', methods: ['POST'])]
    public function apiRequestResetPassword(
        Request $request,
        EntityManagerInterface $em,
        ResetPasswordHelperInterface $resetPasswordHelper,
        MailerInterface $mailer
    ): Response {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        if (!$email) {
            return $this->json(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            // Pour la sécurité, on ne révèle pas si l'email existe
            return $this->json(['message' => 'Si un compte existe, un email a été envoyé.']);
        }
        $resetToken = $resetPasswordHelper->generateResetToken($user);
        $resetUrl = $this->generateUrl('web_reset_password_confirm', ['token' => $resetToken->getToken()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        $emailMessage = (new TemplatedEmail())
            ->from(new Address($this->mailerFromAddress($request)))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/reset_request_email.html.twig')
            ->context(['resetUrl' => $resetUrl]);
        $mailer->send($emailMessage);
        return $this->json(['message' => 'Si un compte existe, un email a été envoyé.']);
    }
}
