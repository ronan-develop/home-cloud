<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;


class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private \App\Service\PasswordResetServiceInterface $passwordResetService,
    ) {}

    #[Route('/reset-password', name: 'web_reset_password', methods: ['GET'])]
    public function webResetPassword(): Response
    {
        return $this->render('reset_password/request.html.twig');
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
        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from('no-reply@homecloud.local')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->text('Pour réinitialiser votre mot de passe, utilisez ce token : ' . $resetToken->getToken());
        $mailer->send($emailMessage);
        return $this->json(['message' => 'Si un compte existe, un email a été envoyé.']);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // Si vous souhaitez indiquer à l'utilisateur pourquoi un email n'a pas été envoyé, décommentez
            // les lignes ci-dessous et changez la redirection vers 'app_forgot_password_request'.
            // Attention : cela peut révéler si un utilisateur est enregistré ou non.
            //
            // $this->addFlash('reset_password_error', sprintf(
            //     '%s - %s',
            //     ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE,
            //     $e->getReason()
            // ));
            return $this->redirectToRoute('app_check_email');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('ronan@lenouvel.me', 'Ronan'))
            ->to((string) $user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ]);

        $mailer->send($email);

        // Stocke l'objet token en session pour récupération dans la route check-email.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
