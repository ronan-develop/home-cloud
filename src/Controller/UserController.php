<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    #[Route('/user', name: 'app_user')]
    public function index(Request $request): Response
    {
        if ($this->getUser() === null) {

            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/index.html.twig', [
        ]);
    }

    #[Route('/user/{id}/edit', name: 'app_user_edit')]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        if ($this->getUser() === null) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserType::class, $user, [
            'required' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('password')->getData();

            if ($plainPassword) {

                $encodedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($encodedPassword);                # code...
            }
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Mis à jour effectuée avec succès');

            return $this->redirectToRoute('app_user');
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $this->getUser(),
        ]);
    }
}