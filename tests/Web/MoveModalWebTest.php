<?php

declare(strict_types=1);

namespace App\Tests\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels UI : déplacement dossier/fichier via la modale web.
 */
final class MoveModalWebTest extends WebTestCase
{
    // TDD RED : tests structurels HTML, pas de JS

    public function testMoveFolderButtonExistsForEachFolder(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('[data-testid^="move-folder-btn-"]')->count(), 'Le bouton déplacer dossier doit exister');
    }

    public function testMoveFileButtonExistsForEachFile(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('[data-testid^="move-file-btn-"]')->count(), 'Le bouton déplacer fichier doit exister');
    }

    public function testGlobalMoveModalExistsInDOM(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('#move-modal')->count(), 'La modale globale doit exister');
        $this->assertGreaterThan(0, $crawler->filter('#move-modal.hidden')->count(), 'La modale doit être fermée par défaut');
    }

    public function testMoveModalHasFolderListArea(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('#move-target-list')->count(), 'La zone de liste des dossiers doit exister');
    }

    public function testMoveModalHasSubmitButton(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('#move-submit-btn')->count(), 'Le bouton confirmer doit exister');
    }

    public function testMoveModalHasTitle(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('#move-modal-title')->count(), 'Le titre de la modale doit exister');
    }

    public function testMoveModalClosedByDefault(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertStringContainsString('hidden', $crawler->filter('#move-modal')->attr('class'), 'La modale doit être fermée par défaut');
    }
}
