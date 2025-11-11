<?php
// src/Controller/PhotoApiController.php

namespace App\Controller;

use App\Repository\PhotoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/photos', name: 'api_photos_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PhotoApiController extends AbstractController
{
    #[Route('/lazy', name: 'lazy', methods: ['GET'])]
    public function lazy(Request $request, PhotoRepository $photoRepository): JsonResponse
    {
        $user = $this->getUser();
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $photos = $photoRepository->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.uploadedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $data = [
            'photos' => array_map(function ($photo) {
                return [
                    'id' => $photo->getId(),
                    'url' => $this->generateUrl('photo_view', ['id' => $photo->getId()]),
                    'title' => $photo->getName(),
                    'originalName' => method_exists($photo, 'getOriginalName') ? $photo->getOriginalName() : null,
                ];
            }, $photos),
        ];
        return $this->json($data);
    }
}
