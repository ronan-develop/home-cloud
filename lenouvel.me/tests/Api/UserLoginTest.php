<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class UserLoginTest extends ApiTestCase
{
    /** @var AbstractDatabaseTool */
    protected $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->databaseTool->loadFixtures([
            \App\DataFixtures\AppFixtures::class
        ]);
    }

    public function test_login_success(): void
    {
        // Vérification de la présence de l'utilisateur de test
        $user = static::getContainer()->get('doctrine')->getRepository(\App\Entity\User::class)->findOneBy(['username' => 'demo']);
        if (!$user) {
            fwrite(STDERR, "[DEBUG] Utilisateur 'demo' absent en base avant login test.\n");
        } else {
            fwrite(STDERR, "[DEBUG] Utilisateur 'demo' trouvé en base (id: {$user->getId()}).\n");
        }
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

    public function test_jwt_env_debug(): void
    {
        $passphrase = $_ENV['JWT_PASSPHRASE'] ?? $_SERVER['JWT_PASSPHRASE'] ?? null;
        $privateKeyPath = $_ENV['JWT_SECRET_KEY'] ?? $_SERVER['JWT_SECRET_KEY'] ?? null;
        $publicKeyPath = $_ENV['JWT_PUBLIC_KEY'] ?? $_SERVER['JWT_PUBLIC_KEY'] ?? null;
        $privateKeyExists = file_exists($privateKeyPath) ? 'oui' : 'non';
        $publicKeyExists = file_exists($publicKeyPath) ? 'oui' : 'non';
        $privateKeyContent = $privateKeyExists === 'oui' ? file_get_contents($privateKeyPath) : '';
        $this->assertNotEmpty($passphrase, 'La passphrase JWT doit être définie');
        $this->assertEquals('adminHomeCloud', $passphrase, 'La passphrase JWT doit être adminHomeCloud');
        $this->assertEquals('oui', $privateKeyExists, 'La clé privée doit exister');
        $this->assertEquals('oui', $publicKeyExists, 'La clé publique doit exister');
    }
}
