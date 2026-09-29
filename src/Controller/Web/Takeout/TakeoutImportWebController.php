<?php

declare(strict_types=1);

namespace App\Controller\Web\Takeout;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page d'import Google Photos Takeout (#327) : sélection du/des ZIP,
 * upload multipart, suivi de la progression par polling — toute la logique
 * métier reste côté API (TakeoutImportCreateController,
 * TakeoutImportFileUploadController, TakeoutImportStartController) et le
 * Stimulus controller frontend ; cette page ne fait que rendre le template.
 */
#[IsGranted('ROLE_USER')]
final class TakeoutImportWebController extends AbstractController
{
    #[Route('/import/takeout', name: 'app_takeout_import', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web/takeout_import.html.twig');
    }
}
