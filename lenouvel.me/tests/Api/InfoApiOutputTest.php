<?php

namespace App\Tests\Api;

use App\Tests\Api\AbstractApiTest;

class InfoApiOutputTest extends AbstractApiTest
{
    public function test_api_info_endpoint_returns_expected_json(): void
    {
        // ⚠️ Important : on force un redémarrage du kernel pour garantir la visibilité des fixtures
        // Voir https://github.com/api-platform/core/issues/6971 et la doc Symfony sur l'isolation des tests
        self::ensureKernelShutdown();
        $token = $this->getJwtToken();
        $response = static::createClient()->request('GET', '/api/info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

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
