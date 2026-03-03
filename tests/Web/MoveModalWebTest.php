<?php

declare(strict_types=1);

namespace App\Tests\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels UI : déplacement dossier/fichier via la modale web.
 */
final class MoveModalWebTest extends WebTestCase
{
    public function testOpenMoveModalAndMoveFolder(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Vérifie que le bouton "Déplacer" est présent pour un dossier
        $this->assertGreaterThan(0, $crawler->filter('[data-testid^="move-folder-btn-"]')->count());

        // Simule le clic sur le bouton "Déplacer" du premier dossier
        $button = $crawler->filter('[data-testid^="move-folder-btn-"]')->first();
        $client->executeScript('openMoveModal("folder", "' . $button->attr('data-testid') . '")');

        // Vérifie que la modale s'affiche
        $this->assertFalse($crawler->filter('#move-modal')->hasClass('hidden'));
    }

    public function testOpenMoveModalAndMoveFile(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Vérifie que le bouton "Déplacer" est présent pour un fichier
        $this->assertGreaterThan(0, $crawler->filter('[data-testid^="move-file-btn-"]')->count());

        // Simule le clic sur le bouton "Déplacer" du premier fichier
        $button = $crawler->filter('[data-testid^="move-file-btn-"]')->first();
        $client->executeScript('openMoveModal("file", "' . $button->attr('data-testid') . '")');

        // Vérifie que la modale s'affiche
        $this->assertFalse($crawler->filter('#move-modal')->hasClass('hidden'));
    }

    // À compléter : tests de soumission PATCH, toast, reload, erreurs, etc.
}
