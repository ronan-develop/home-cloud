<?php

namespace App\Uploader;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Twig\Environment;

class UploadFeedbackManager
{
    private RequestStack $requestStack;
    private Environment $twig;

    public function __construct(RequestStack $requestStack, Environment $twig)
    {
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    public function success(Request $request, string $message): RedirectResponse
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if ($session) {
            $flashBag = $session->getBag('flashes');
            if ($flashBag instanceof FlashBag) {
                $flashBag->add('success', $message);
            }
        }
        return new RedirectResponse($request->getUri());
    }

    public function error(FormInterface $form, string $message): Response
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if ($message && $session) {
            $flashBag = $session->getBag('flashes');
            if ($flashBag instanceof FlashBag) {
                $flashBag->add('danger', $message);
            }
        }
        $html = $this->twig->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
        return new Response($html);
    }
}
