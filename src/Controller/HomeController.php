<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\FileRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    public function index(Request $request, FileRepository $fileRepository): Response
    {
        $user = $this->getUser();
        $filesPager = null;
        $lastFile = null;
        if ($user) {
            $page = max(1, (int) $request->query->get('page', 1));
            $query = $fileRepository->getFilesForUserQuery($user);
            $pagerfanta = new Pagerfanta(new QueryAdapter($query));
            $pagerfanta->setMaxPerPage(10);
            $pagerfanta->setCurrentPage($page);
            $filesPager = $pagerfanta;
            $lastFile = $fileRepository->getLastFileForUser($user);
        }
        return $this->render('home/index.html.twig', [
            'filesPager' => $filesPager,
            'lastFile' => $lastFile,
        ]);
    }
}
