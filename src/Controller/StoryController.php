<?php

namespace App\Controller;

use App\Entity\Story;
use App\Form\StoryType;
use App\Repository\StoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/story')]
class StoryController extends AbstractController
{
    /**
     * gett all stories. Base path
     *
     * @param StoryRepository $storyRepository
     * @return Response
     */
    #[Route('/', name: 'app_story_index', methods: ['GET'])]
    public function index(StoryRepository $storyRepository): Response
    {
        return $this->render('story/index.html.twig', [
            'stories' => $storyRepository->findAll(),
        ]);
    }

    /**
     * Create new story
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/new', name: 'app_story_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $story = new Story();
        $form = $this->createForm(StoryType::class, $story);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($story);
            $entityManager->flush();

            return $this->redirectToRoute('app_story_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('story/new.html.twig', [
            'story' => $story,
            'form' => $form,
        ]);
    }

    /**
     * Show one story
     * 
     * @param int $id
     * @return Response
     */
    #[Route('/{id}', name: 'app_story_show', methods: ['GET'])]
    public function show(Story $story): Response
    {
        return $this->render('story/show.html.twig', [
            'story' => $story,
        ]);
    }

    /**
     * Edit one story
     * 
     * @param int $id
     * @param Request $requet
     * @param Story $story
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_story_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Story $story, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StoryType::class, $story);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_story_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('story/edit.html.twig', [
            'story' => $story,
            'form' => $form,
        ]);
    }

    /**
     * Delete one story
     * 
     * @param Requeste $request
     * @param Story $story
     * @param EntityManagerInterface $entityMAnager
     * @return Response
     */
    #[Route('/{id}', name: 'app_story_delete', methods: ['POST'])]
    public function delete(Request $request, Story $story, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$story->getId(), $request->request->get('_token'))) {
            $entityManager->remove($story);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_story_index', [], Response::HTTP_SEE_OTHER);
    }
}
