<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Marquage discret de lastChangelogViewedAt (#411) — même effet que visiter
 * /changelog (cf. ChangelogController::index), sans re-render la page.
 * Déclenché au clic sur une entrée changelog du dropdown de notifications :
 * la PR GitHub s'ouvre dans un nouvel onglet (target="_blank"), cette route
 * se joue en fond dans l'onglet d'origine pour que l'utilisateur voie
 * l'item disparaître sans avoir à revenir sur la page.
 */
#[IsGranted('ROLE_USER')]
final class ChangelogMarkViewedWebController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/changelog/mark-viewed', name: 'app_changelog_mark_viewed', methods: ['POST'])]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setLastChangelogViewedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new Response(status: 204);
    }
}
