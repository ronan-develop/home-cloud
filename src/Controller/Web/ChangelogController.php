<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Interface\ChangelogFetcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * #290 : grands thèmes de l'historique du projet (date + titre + lien
 * GitHub), alimenté automatiquement depuis les PR mergées.
 */
#[IsGranted('ROLE_USER')]
final class ChangelogController extends AbstractController
{
    public function __construct(
        private readonly ChangelogFetcherInterface $changelogFetcher,
    ) {}

    #[Route('/changelog', name: 'app_changelog', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web/changelog.html.twig', [
            'entries' => $this->changelogFetcher->fetchEntries(),
        ]);
    }
}
