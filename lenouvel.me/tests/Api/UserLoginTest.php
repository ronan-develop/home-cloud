<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class UserLoginTest extends AbstractApiTest
{
    public function test_login_success(): void
    {
        $response = static::createClient()->request('POST', '/api/login_check', [
            'json' => [
                'email' => 'demo@homecloud.local',
                'password' => 'test' // Doit correspondre à la fixture UserFixture
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function test_login_failure(): void
    {
        $response = static::createClient()->request('POST', '/api/login_check', [
            'json' => [
                'email' => 'demo@homecloud.local',
                'password' => 'wrongpassword'
            ]
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertArrayHasKey('code', $response->toArray()); // JWT retourne un code d'erreur
    }
}
