<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : endpoint interne inter-instances du monitoring admin
 * (#376) — même mécanisme d'authentification que le broadcast (#283),
 * secret partagé (X-Broadcast-Token), appel service-to-service entre
 * instances.
 */
final class MonitoringInternalControllerTest extends WebTestCase
{
    public function testRejects401WithoutToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/internal/monitoring');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testRejects401WithWrongToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/internal/monitoring', server: [
            'HTTP_X_BROADCAST_TOKEN' => 'mauvais-token',
        ]);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testAcceptsValidTokenAndReturnsSnapshot(): void
    {
        $client = static::createClient();
        $client->request('GET', '/internal/monitoring', server: [
            'HTTP_X_BROADCAST_TOKEN' => $_ENV['BROADCAST_SHARED_TOKEN'],
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('userCount', $data);
        $this->assertArrayHasKey('totalStorageBytes', $data);
        $this->assertArrayHasKey('gitRevision', $data);
    }
}
