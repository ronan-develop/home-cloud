<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class InfoApiOutputTest extends ApiTestCase
{
    public function test_api_info_endpoint_returns_expected_json(): void
    {
        $response = static::createClient()->request('GET', '/api/info');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/ld+json; charset=utf-8', $response->getHeaders()['content-type'][0]);
        $this->assertJsonContains([
            'message' => 'Bienvenue sur l’API Home Cloud.',
            'version' => '1.0.0',
            'login_endpoint' => '/api/login',
            'info' => 'Authentifiez-vous via POST /api/login avec vos credentials (email/username + password).',
        ]);
    }
}
