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

    public function testMoveFolderNominal(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Sélectionne le premier bouton "Déplacer" dossier
        $button = $crawler->filter('[data-testid^="move-folder-btn-"]')->first();
        $folderId = $button->attr('data-testid');
        $client->executeScript('openMoveModal("folder", "' . $folderId . '")');

        // Sélectionne le dossier cible (autre que le courant)
        $targetBtn = $crawler->filter('.move-target[data-folder-id]:not([data-folder-id="' . $folderId . '"])')->first();
        $targetId = $targetBtn->attr('data-folder-id');
        $client->executeScript('selectMoveTarget("' . $targetId . '", document.querySelector(".move-target[data-folder-id=\"' . $targetId . '\"]"))');

        // Soumet le déplacement
        $client->executeScript('submitMove()');

        // Vérifie le toast de succès
        $this->assertSelectorTextContains('.fixed.bottom-4.right-4', 'Déplacement réussi');

        // Vérifie le reload (optionnel)
        // $this->assertTrue($client->getResponse()->isRedirection());
    }

    public function testMoveFileNominal(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Sélectionne le premier bouton "Déplacer" fichier
        $button = $crawler->filter('[data-testid^="move-file-btn-"]')->first();
        $fileId = $button->attr('data-testid');
        $client->executeScript('openMoveModal("file", "' . $fileId . '")');

        // Sélectionne le dossier cible
        $targetBtn = $crawler->filter('.move-target[data-folder-id]')->first();
        $targetId = $targetBtn->attr('data-folder-id');
        $client->executeScript('selectMoveTarget("' . $targetId . '", document.querySelector(".move-target[data-folder-id=\"' . $targetId . '\"]"))');

        // Soumet le déplacement
        $client->executeScript('submitMove()');

        // Vérifie le toast de succès
        $this->assertSelectorTextContains('.fixed.bottom-4.right-4', 'Déplacement réussi');
    }

    public function testMoveFolderCycleError(): void
    {
        // À compléter : simuler un déplacement cyclique et vérifier le message d'erreur
    }

    public function testMoveFileOwnershipError(): void
    {
        // À compléter : simuler un déplacement vers un dossier d'un autre user et vérifier le message d'erreur
    }

    public function testMoveFileNotFoundError(): void
    {
        // À compléter : simuler un déplacement d'un fichier inexistant et vérifier le message d'erreur
    }

    public function testMoveFileWithoutAuthError(): void
    {
        // À compléter : simuler une soumission sans authentification et vérifier le code 401
    }
}
