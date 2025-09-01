<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Integration\DatabaseResetTrait;

class PrivateSpaceCrudTest extends ApiTestCase
{
    use DatabaseResetTrait;
    /**
     * Reset la base et recharge les fixtures avant chaque test fonctionnel API.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Reset base et fixtures (pattern obligatoire API Platform)
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
    }

    public function testCreatePrivateSpace(): void
    {
        $response = static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Test',
                'description' => 'Un espace de test.'
            ])
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['name' => 'Espace Test', 'description' => 'Un espace de test.']);
    }

    public function testCreatePrivateSpaceInvalid(): void
    {
        $response = static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'description' => 'Sans nom.'
            ])
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'name']
            ]
        ]);
    }

    public function testCreatePrivateSpaceInvalidDescription(): void
    {
        $response = static::createClient()->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Sans description'
            ])
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'description']
            ]
        ]);
    }

    public function testGetPrivateSpaceCollection(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Coll',
                'description' => 'Pour la collection.'
            ])
        ]);
        $response = $client->request('GET', '/api/private_spaces', [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray(false);
        // API Platform v3+ retourne 'member' au lieu de 'hydra:member' par défaut
        $this->assertArrayHasKey('member', $data, "La clé 'member' doit être présente dans la réponse.");
        $names = array_column($data['member'], 'name');
        $this->assertContains('Espace Coll', $names, 'La collection doit contenir l\'espace créé.');
    }

    public function testGetPrivateSpaceItem(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Item',
                'description' => 'Pour l\'item.'
            ])
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
            'body' => json_encode([
                'name' => 'Espace Update',
                'description' => 'Avant modif.'
            ])
        ]);
        $id = $response->toArray()['id'] ?? null;
        $client->request('PUT', '/api/private_spaces/' . $id, [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Update',
                'description' => 'Après modif.'
            ])
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['description' => 'Après modif.']);
    }

    public function testUpdateNonExistentPrivateSpace(): void
    {
        static::createClient()->request('PUT', '/api/private_spaces/99999', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Inexistant',
                'description' => 'Inexistant.'
            ])
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeletePrivateSpace(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Delete',
                'description' => 'À supprimer.'
            ])
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

    public function testFixturesAreVisibleViaDoctrine(): void
    {
        // Récupère le container de test
        self::bootKernel();
        $container = static::getContainer();
        $repo = $container->get(\App\Repository\PrivateSpaceRepository::class);
        $spaces = $repo->findAll();
        $this->assertNotEmpty($spaces, 'Les entités PrivateSpace doivent être présentes en base après chargement des fixtures.');
        $names = array_map(fn($s) => $s->getName(), $spaces);
        $this->assertContains('Espace Démo', $names, 'La fixture "Espace Démo" doit être présente.');
    }

    public function testPrivateSpaceUserRelation(): void
    {
        $client = static::createClient();

        // Création d'un utilisateur via l'API
        $responseUser = $client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'username' => 'relation_user',
                'email' => 'relation_user@example.com',
                'password' => 'testpass'
            ])
        ]);
        $this->assertResponseStatusCodeSame(201);
        $userIri = $responseUser->toArray()['@id'] ?? null;
        $this->assertNotNull($userIri, 'L’IRI de l’utilisateur doit être présent.');

        // Création d’un PrivateSpace lié à cet utilisateur
        $responseSpace = $client->request('POST', '/api/private_spaces', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Espace Relation',
                'description' => 'Test relation User <-> PrivateSpace',
                'user' => $userIri
            ])
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $responseSpace->toArray();
        $this->assertEquals($userIri, $data['user'], 'Le PrivateSpace doit être lié à l’utilisateur créé.');

        // Vérification côté GET
        $spaceIri = $data['@id'] ?? null;
        $responseGet = $client->request('GET', $spaceIri, [
            'headers' => ['Accept' => 'application/ld+json']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertEquals($userIri, $responseGet->toArray()['user']);
    }
}
