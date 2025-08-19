<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class UserLoginTest extends ApiTestCase
{
    public function test_login_success(): void
    {
        // Préparer un utilisateur en base (à adapter selon fixtures ou factory)
        // ...
        $response = static::createClient()->request('POST', '/api/login_check', [
            'json' => [
                'username' => 'demo',
                'password' => 'password123'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function test_login_failure(): void
    {
        $response = static::createClient()->request('POST', '/api/login_check', [
            'json' => [
                'username' => 'demo',
                'password' => 'wrongpassword'
            ]
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertArrayHasKey('code', $response->toArray()); // JWT retourne un code d'erreur
    }
}
