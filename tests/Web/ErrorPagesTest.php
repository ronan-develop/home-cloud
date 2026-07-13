<?php

declare(strict_types=1);

namespace App\Tests\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels — Pages d'erreur personnalisées (404, 403, 500...).
 *
 * TDD RED : ces tests doivent d'abord échouer, puis passer après implémentation.
 */
final class ErrorPagesTest extends WebTestCase
{
    /**
     * Le kernel de test tourne avec kernel.debug=true, qui fait toujours passer
     * Symfony par la page de debug plutôt que nos templates custom (cf.
     * TwigErrorRenderer::render()). On teste donc directement le rendu Twig du
     * template, ce qui est représentatif du HTML réellement produit en prod
     * (kernel.debug=false) sans dépendre du mode debug de l'environnement de test.
     */
    private function renderErrorTemplate(int $statusCode, string $statusText = 'Error'): string
    {
        static::createClient();
        $twig = static::getContainer()->get(\Twig\Environment::class);

        return $twig->render('bundles/TwigBundle/Exception/error.html.twig', [
            'status_code' => $statusCode,
            'status_text' => $statusText,
        ]);
    }

    public function testNotFoundPageShowsCustomTemplate(): void
    {
        $html = $this->renderErrorTemplate(404, 'Not Found');

        $this->assertStringContainsString('data-testid="error-page"', $html);
        $this->assertStringContainsString('404', $html);
        $this->assertStringContainsString('Page introuvable', $html);
    }

    public function testNotFoundPageHasLinkBackHome(): void
    {
        $html = $this->renderErrorTemplate(404, 'Not Found');

        $this->assertStringContainsString('data-testid="error-back-home"', $html);
    }

    public function testForbiddenPageShowsCustomTemplate(): void
    {
        $html = $this->renderErrorTemplate(403, 'Forbidden');

        $this->assertStringContainsString('data-testid="error-page"', $html);
        $this->assertStringContainsString('403', $html);
        $this->assertStringContainsString('Accès refusé', $html);
    }

    public function testBadRequestPageShowsCustomTemplate(): void
    {
        $html = $this->renderErrorTemplate(400, 'Bad Request');

        $this->assertStringContainsString('400', $html);
        $this->assertStringContainsString('Requête invalide', $html);
    }

    public function testServerErrorPageShowsCustomTemplate(): void
    {
        $html = $this->renderErrorTemplate(500, 'Internal Server Error');

        $this->assertStringContainsString('data-testid="error-page"', $html);
        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('Erreur serveur', $html);
    }

    public function testErrorTemplateResolutionIsRegisteredForCommonStatusCodes(): void
    {
        static::createClient();
        $twig = static::getContainer()->get(\Twig\Environment::class);
        $loader = $twig->getLoader();

        $this->assertTrue($loader->exists('bundles/TwigBundle/Exception/error.html.twig'));
    }
}
