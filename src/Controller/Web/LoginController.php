<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Gère la connexion et déconnexion de l'interface web (session Symfony).
 * Séparé du firewall JWT /api — ce controller est uniquement pour le front Twig.
 */
final class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // ?email= pré-remplit le champ (ex: après un reset de mot de passe
        // invité) — pur confort UX, le mot de passe reste toujours requis et
        // vérifié par form_login, aucun accès n'est accordé par ce paramètre.
        return $this->render('web/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername() ?: (string) $request->query->get('email', ''),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le firewall Symfony.');
    }
}
