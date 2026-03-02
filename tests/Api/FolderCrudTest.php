<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Folder;

class FolderCrudTest extends ApiTestCase
{
    public function testCreateFolder(): void
    {
        $response = static::createClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'TestFolder',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'TestFolder',
        ]);
    }

    // TODO: Ajouter tests GET, PUT, DELETE, erreurs, droits, etc.
}
