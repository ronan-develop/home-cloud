<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;

class PrivateSpaceCrudTest extends ApiTestCase
{
    use RefreshDatabaseTrait;

    public function testCreatePrivateSpace(): void
    {
        $response = static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Test',
                'description' => 'Un espace de test.'
            ]
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['name' => 'Espace Test', 'description' => 'Un espace de test.']);
    }

    public function testCreatePrivateSpaceInvalid(): void
    {
        $response = static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'description' => 'Sans nom.'
            ]
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'name']
            ]
        ]);
    }

    public function testGetPrivateSpaceCollection(): void
    {
        static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Coll',
                'description' => 'Pour la collection.'
            ]
        ]);
        $response = static::createClient()->request('GET', '/api/private_spaces', [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['hydra:member' => [['name' => 'Espace Coll']]]);
    }

    public function testGetPrivateSpaceItem(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Item',
                'description' => 'Pour l\'item.'
            ]
        ]);
        $id = $response->toArray()['id'] ?? null;
        $client->request('GET', '/api/private_spaces/' . $id, [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['name' => 'Espace Item']);
    }

    public function testGetNonExistentPrivateSpace(): void
    {
        static::createClient()->request('GET', '/api/private_spaces/99999', [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdatePrivateSpace(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Update',
                'description' => 'Avant modif.'
            ]
        ]);
        $id = $response->toArray()['id'] ?? null;
        $client->request('PUT', '/api/private_spaces/' . $id, [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Update',
                'description' => 'Après modif.'
            ]
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['description' => 'Après modif.']);
    }

    public function testUpdateNonExistentPrivateSpace(): void
    {
        static::createClient()->request('PUT', '/api/private_spaces/99999', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Inexistant',
                'description' => 'Inexistant.'
            ]
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeletePrivateSpace(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'name' => 'Espace Delete',
                'description' => 'À supprimer.'
            ]
        ]);
        $id = $response->toArray()['id'] ?? null;
        $client->request('DELETE', '/api/private_spaces/' . $id, [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseStatusCodeSame(204);
        $client->request('GET', '/api/private_spaces/' . $id, [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteNonExistentPrivateSpace(): void
    {
        static::createClient()->request('DELETE', '/api/private_spaces/99999', [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseStatusCodeSame(404);
    }
}
