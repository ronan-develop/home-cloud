<?php

namespace App\File;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileErrorRedirectorService
{
    private RequestStack $requestStack;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(RequestStack $requestStack, UrlGeneratorInterface $urlGenerator)
    {
        $this->requestStack = $requestStack;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Gère la redirection en cas d'erreur d'accès ou de fichier inexistant.
     * @param string $errorMsg
     * @param string $route
     * @return RedirectResponse
     */
    public function handle(string $errorMsg, string $route = 'file_upload'): RedirectResponse
    {
        $session = $this->requestStack->getSession();
        if ($session) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $session->getBag('flashes');
            $flashBag->add('danger', $errorMsg);
        }
        $url = $this->urlGenerator->generate($route);
        return new RedirectResponse($url);
    }
}
