<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class UserSettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('web/settings.html.twig', [
            'user' => $user,
        ]);
    }
}
