<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    private function buildEvent(string $path = '/api/v1/users', ?string $route = null): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }
        $response = new Response();

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    public function testCommonSecurityHeadersAreAlwaysPresent(): void
    {
        $event = $this->buildEvent();
        (new SecurityHeadersListener('test'))($event);

        $headers = $event->getResponse()->headers;
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $headers->get('X-Frame-Options'));
        $this->assertSame('no-referrer', $headers->get('Referrer-Policy'));
    }

    public function testCspHeaderIsSetOnApiRoutes(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('test'))($event);

        $this->assertSame("default-src 'none'", $event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testCspHeaderIsNotSetOnApiDocs(): void
    {
        $event = $this->buildEvent('/api/docs');
        (new SecurityHeadersListener('test'))($event);

        $this->assertNull($event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testHstsHeaderIsPresentInProd(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('prod'))($event);

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $event->getResponse()->headers->get('Strict-Transport-Security')
        );
    }

    public function testHstsHeaderIsAbsentInDev(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('dev'))($event);

        $this->assertNull($event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testHstsHeaderIsAbsentInTest(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('test'))($event);

        $this->assertNull($event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    /**
     * F7 de l'audit sécurité : le front HTML n'avait aucune CSP, alors qu'il
     * rend du contenu utilisateur (noms de fichiers/albums). La CSP reste
     * permissive sur les scripts inline existants (pas de refonte en nonces),
     * mais bloque l'injection de sources EXTERNES — le vecteur XSS le plus
     * dangereux (ex: <script src="https://evil.com">).
     */
    public function testCspHeaderIsSetOnHtmlFrontRoutes(): void
    {
        $event = $this->buildEvent('/explorer');
        (new SecurityHeadersListener('test'))($event);

        $csp = $event->getResponse()->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function testCspHeaderOnHtmlFrontAllowsInlineScriptsAndStyles(): void
    {
        $event = $this->buildEvent('/gallery');
        (new SecurityHeadersListener('test'))($event);

        $csp = $event->getResponse()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' data:", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    /**
     * AssetMapper mappe les imports CSS des modules JS (import './styles/app.css')
     * vers un faux module data:application/javascript, — sans data: dans
     * script-src, ce faux module est bloqué par la CSP, ce qui interrompt toute
     * la chaîne d'imports du module JS principal (app.js et tout ce qu'il importe :
     * modal.js, move-modal.js, etc.). Régression constatée : la recherche live
     * (dépend de openMoveElementModal, définie dans move-modal.js) ne fonctionnait
     * plus du tout après l'introduction de la CSP stricte (commit 6b494cd).
     */
    public function testCspAllowsDataUriInScriptSrcForAssetMapperCssModules(): void
    {
        $event = $this->buildEvent('/explorer');
        (new SecurityHeadersListener('test'))($event);

        $csp = $event->getResponse()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('data:', $csp);
    }

    public function testCspHeaderIsStrictOnApiRoutesNotHtmlPolicy(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('test'))($event);

        $this->assertSame("default-src 'none'", $event->getResponse()->headers->get('Content-Security-Policy'));
    }

    /**
     * #280 : le viewer PDF (#241) embarque /files/{id}/view dans une <iframe>
     * same-origin. X-Frame-Options: DENY et frame-ancestors 'none' bloquent
     * cet embedding inconditionnellement, y compris depuis la même origine —
     * la dérogation est strictement scopée à cette route pour ne pas
     * affaiblir la protection anti-clickjacking ailleurs.
     */
    public function testFileViewRouteAllowsSameOriginFraming(): void
    {
        $event = $this->buildEvent('/files/0198c1b2-6b8b-7f3e-8a1a-000000000001/view', 'app_file_view');
        (new SecurityHeadersListener('test'))($event);

        $headers = $event->getResponse()->headers;
        $this->assertSame('SAMEORIGIN', $headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'self'", $headers->get('Content-Security-Policy'));
    }

    public function testOtherFrontRoutesStillDenyFraming(): void
    {
        $event = $this->buildEvent('/files/0198c1b2-6b8b-7f3e-8a1a-000000000001/download', 'app_file_download');
        (new SecurityHeadersListener('test'))($event);

        $headers = $event->getResponse()->headers;
        $this->assertSame('DENY', $headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", $headers->get('Content-Security-Policy'));
    }

    /**
     * #285 : la dérogation était basée sur un regex du path, dupliqué de
     * l'attribut #[Route] — une route différente qui matcherait
     * accidentellement le même pattern de path ne doit pas en bénéficier.
     */
    public function testPathMatchingFileViewPatternButDifferentRouteNameDoesNotGetDeroga(): void
    {
        $event = $this->buildEvent('/files/0198c1b2-6b8b-7f3e-8a1a-000000000001/view', 'app_some_other_route');
        (new SecurityHeadersListener('test'))($event);

        $headers = $event->getResponse()->headers;
        $this->assertSame('DENY', $headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", $headers->get('Content-Security-Policy'));
    }
}
