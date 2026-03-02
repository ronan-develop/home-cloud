<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\FolderTreeService;
use App\Entity\Folder;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

class FolderTreeServiceTest extends TestCase
{
    public function testBuildTreeSimple()
    {
        $user = new User('test@homecloud.local', 'Test');
        $root = new Folder('Dossiers', $user, null);
        $service = new FolderTreeService();

        $tree = $service->buildTree($root);

        $this->assertEquals('Dossiers', $tree['name']);
        $this->assertEquals([], $tree['children']);
    }

    public function testBuildTreeImbriquee()
    {
        $user = new User('test@homecloud.local', 'Test');
        $root = new Folder('Dossiers', $user, null);
        $toto = new Folder('toto', $user, $root);
        $tata = new Folder('tata', $user, $toto);
        $root->getChildren()->add($toto);
        $toto->getChildren()->add($tata);
        $service = new FolderTreeService();

        $tree = $service->buildTree($root);

        $this->assertEquals('Dossiers', $tree['name']);
        $this->assertCount(1, $tree['children']);
        $this->assertEquals('toto', $tree['children'][0]['name']);
        $this->assertCount(1, $tree['children'][0]['children']);
        $this->assertEquals('tata', $tree['children'][0]['children'][0]['name']);
    }
}
