<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class UserLoginTest extends ApiTestCase
{
    public function test_login_success(): void
    {
        // Préparer un utilisateur en base (à adapter selon fixtures ou factory)
        // ...
        $response = static::createClient()->request('POST', '/api/login', [
            'json' => [
                'username' => 'demo',
                'password' => 'password123'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'token' => true // ou autre structure selon la réponse attendue
        ]);
    }

    public function test_login_failure(): void
    {
        $response = static::createClient()->request('POST', '/api/login', [
            'json' => [
                'username' => 'demo',
                'password' => 'wrongpassword'
            ]
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'message' => 'Invalid credentials.'
        ]);
    }
}
