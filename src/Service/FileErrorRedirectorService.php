<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileErrorRedirectorService
{
    private FlashBagInterface $flashBag;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(FlashBagInterface $flashBag, UrlGeneratorInterface $urlGenerator)
    {
        $this->flashBag = $flashBag;
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
        $this->flashBag->add('danger', $errorMsg);
        $url = $this->urlGenerator->generate($route);
        return new RedirectResponse($url);
    }
}
