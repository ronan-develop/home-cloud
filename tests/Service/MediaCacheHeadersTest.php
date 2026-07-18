<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MediaCacheHeaders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toutes les routes servant des images répondaient en max-age=0 : le navigateur
 * ne gardait rien, et chaque scroll dans une galerie retéléchargeait les
 * vignettes déjà vues. Sur 200 photos consultées 5 fois, c'est 1000 requêtes là
 * où 200 suffisent.
 *
 * Une image est pourtant immuable : une vignette, une fois générée, ne change
 * plus jamais — seule sa suppression la rend obsolète, et la route répond alors
 * 404.
 */
final class MediaCacheHeadersTest extends TestCase
{
    public function testMarksResponseAsPrivatelyCacheable(): void
    {
        $response = new Response();

        (new MediaCacheHeaders())->applyTo($response);

        $cacheControl = (string) $response->headers->get('Cache-Control');

        // Privé : un média authentifié ne doit jamais être servi par un cache
        // partagé à un autre utilisateur.
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('no-cache', $cacheControl);
    }

    public function testCachesLongEnoughToSurviveABrowsingSession(): void
    {
        $response = new Response();

        (new MediaCacheHeaders())->applyTo($response);

        $maxAge = $response->getMaxAge();
        $this->assertNotNull($maxAge);
        $this->assertGreaterThanOrEqual(3600, $maxAge, 'Au moins une heure de navigation');
    }

    public function testAllowsSharedCachingForPublicLinks(): void
    {
        // Un partage par lien est accessible sans compte : le contenu peut être
        // servi par un cache partagé, le secret étant dans l'URL elle-même.
        $response = new Response();

        (new MediaCacheHeaders())->applyTo($response, shared: true);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('private', $cacheControl);
    }

    public function testKeepsExistingHeadersUntouched(): void
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        (new MediaCacheHeaders())->applyTo($response);

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
