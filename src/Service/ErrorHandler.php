<?php

namespace App\Service;

use Throwable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class ErrorHandler
{
    private LoggerInterface $logger;
    private Environment $twig;

    public function __construct(LoggerInterface $logger, Environment $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    public function handle(Throwable $exception, string $template = 'error/generic.html.twig', array $context = []): Response
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
        ]);
        $context['error_message'] = $exception->getMessage();
        $html = $this->twig->render($template, $context);
        return new Response($html, 500);
    }
}
