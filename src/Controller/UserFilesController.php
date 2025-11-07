<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\FileRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserFilesController extends AbstractController
{
    #[Route('/mes-fichiers', name: 'user_files')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, FileRepository $fileRepository): Response
    {
        $user = $this->getUser();
        $page = max(1, (int) $request->query->get('page', 1));
        $query = $fileRepository->getFilesForUserQuery($user);
        $pagerfanta = new Pagerfanta(new QueryAdapter($query));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($page);

        return $this->render('file/list.html.twig', [
            'filesPager' => $pagerfanta,
        ]);
    }
}
