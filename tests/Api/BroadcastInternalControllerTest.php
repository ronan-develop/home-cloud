<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : endpoint interne inter-instances du broadcast admin
 * (#283). Authentification par secret partagé (X-Broadcast-Token), hors du
 * firewall JWT `api` — pas de notion d'utilisateur ici, c'est un appel
 * service-to-service entre instances.
 */
final class BroadcastInternalControllerTest extends WebTestCase
{
    public function testRejects401WithoutToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/broadcast', server: ['CONTENT_TYPE' => 'application/json'], content: '{"subject":"S","body":"B"}');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testRejects401WithWrongToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/broadcast', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BROADCAST_TOKEN' => 'mauvais-token',
        ], content: '{"subject":"S","body":"B"}');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testAcceptsValidTokenAndSendsLocally(): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/broadcast', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BROADCAST_TOKEN' => $_ENV['BROADCAST_SHARED_TOKEN'],
        ], content: '{"subject":"Maintenance","body":"<p>Texte</p>","dryRun":false}');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testDryRunReturns200WithoutSendingAnyEmail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/broadcast', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BROADCAST_TOKEN' => $_ENV['BROADCAST_SHARED_TOKEN'],
        ], content: '{"subject":"Maintenance","body":"<p>Texte</p>","dryRun":true}');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        self::assertEmailCount(0);
    }
}
