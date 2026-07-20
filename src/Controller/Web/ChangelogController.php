<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Interface\ChangelogFetcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * #290 : grands thèmes de l'historique du projet (date + titre + lien
 * GitHub), alimenté automatiquement depuis les PR mergées. Paginé : l'API
 * GitHub peut renvoyer des dizaines de thèmes sur toute l'histoire du projet.
 */
#[IsGranted('ROLE_USER')]
final class ChangelogController extends AbstractController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ChangelogFetcherInterface $changelogFetcher,
    ) {}

    #[Route('/changelog', name: 'app_changelog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $allEntries = $this->changelogFetcher->fetchEntries();
        $totalPages = max(1, (int) ceil(count($allEntries) / self::PER_PAGE));

        $page = max(1, min($totalPages, $request->query->getInt('page', 1)));
        $offset = ($page - 1) * self::PER_PAGE;

        return $this->render('web/changelog.html.twig', [
            'entries' => array_slice($allEntries, $offset, self::PER_PAGE),
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
