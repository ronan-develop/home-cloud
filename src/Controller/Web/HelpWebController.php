<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Help\HelpTopicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page d'aide utilisateur (#529) : wiki simple, contenu statique — HomeCloud
 * n'avait aucune documentation orientée utilisateur final, seulement de la
 * doc technique (README, .claude/*.md) destinée aux développeurs. Sous
 * /doc, préfixe parent réservé à la documentation utilisateur. Un sommaire
 * (/doc/aide) liste les sujets, chacun sur sa propre sous-page
 * (/doc/aide/{slug}) — un seul controller pour tous les sujets (pas un
 * controller par sujet), le contenu vit dans HelpTopicRepository.
 */
#[IsGranted('ROLE_USER')]
final class HelpWebController extends AbstractController
{
    public function __construct(
        private readonly HelpTopicRepository $topicRepository,
    ) {}

    #[Route('/doc/aide', name: 'app_help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web/help_index.html.twig', [
            'topics' => $this->topicRepository->findAll(),
        ]);
    }

    #[Route('/doc/aide/{slug}', name: 'app_help_topic', methods: ['GET'])]
    public function topic(string $slug): Response
    {
        $topic = $this->topicRepository->find($slug);
        if ($topic === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('web/help_topic.html.twig', [
            'topic' => $topic,
        ]);
    }
}
