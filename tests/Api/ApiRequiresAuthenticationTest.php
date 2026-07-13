<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sécurité — l'API ne doit jamais répondre à un appel non authentifié.
 *
 * Contexte : le firewall `api` déclare `jwt: ~`, ce qui active l'authentificateur
 * JWT mais n'exige PAS d'être authentifié. Sans `access_control`, toute route API
 * non couverte par un #[IsGranted] répondait 200 à un anonyme (fuite de données).
 *
 * Le front appelle l'API avec un `Authorization: Bearer <jwt>` obtenu via le pont
 * session→JWT (GET /web/token), donc exiger ROLE_USER sur ^/api ne le casse pas.
 */
final class ApiRequiresAuthenticationTest extends WebTestCase
{
    /**
     * Routes API qui manipulent des données utilisateur : doivent exiger l'authentification.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function protectedApiRoutes(): iterable
    {
        $uuid = '00000000-0000-0000-0000-000000000000';

        yield 'files collection'   => ['GET', '/api/v1/files'];
        yield 'file item'          => ['GET', '/api/v1/files/' . $uuid];
        yield 'file download'      => ['GET', '/api/v1/files/' . $uuid . '/download'];
        yield 'folders collection' => ['GET', '/api/v1/folders'];
        yield 'folder children'    => ['GET', '/api/v1/folders/' . $uuid . '/children'];
        yield 'medias collection'  => ['GET', '/api/v1/medias'];
        yield 'media thumbnail'    => ['GET', '/api/v1/medias/' . $uuid . '/thumbnail'];
        yield 'albums collection'  => ['GET', '/api/v1/albums'];
        yield 'shares collection'  => ['GET', '/api/v1/shares'];
        yield 'users collection'   => ['GET', '/api/v1/users'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('protectedApiRoutes')]
    public function testApiRouteRejectsUnauthenticatedRequest(string $method, string $path): void
    {
        $client = static::createClient();
        $client->request($method, $path, server: ['HTTP_ACCEPT' => 'application/json']);

        $status = $client->getResponse()->getStatusCode();

        $this->assertSame(
            401,
            $status,
            sprintf('%s %s doit renvoyer 401 sans authentification, a renvoyé %d.', $method, $path, $status),
        );
    }

    /**
     * Routes qui DOIVENT rester publiques — un 401 ici casserait la connexion
     * ou la réinitialisation de mot de passe (non-régression).
     *
     * @return iterable<string, array{string, string}>
     */
    public static function publicRoutes(): iterable
    {
        yield 'login'                => ['POST', '/api/v1/auth/login'];
        yield 'token refresh'        => ['POST', '/api/v1/auth/token/refresh'];
        yield 'reset password'       => ['POST', '/api/reset-password'];
        yield 'request reset'        => ['POST', '/api/request-reset-password'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicRoutes')]
    public function testPublicRouteStaysReachableWithoutAuthentication(string $method, string $path): void
    {
        $client = static::createClient();
        $client->request($method, $path, server: [
            'HTTP_ACCEPT'  => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        $status = $client->getResponse()->getStatusCode();

        // Le corps est vide/invalide : on attend une erreur métier (400/401 credentials),
        // mais surtout PAS un refus d'accès du firewall (403).
        $this->assertNotSame(
            403,
            $status,
            sprintf('%s %s doit rester joignable sans authentification, a renvoyé 403.', $method, $path),
        );
    }
}
