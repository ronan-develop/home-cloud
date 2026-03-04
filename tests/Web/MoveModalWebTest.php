<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels UI : structure HTML de la modale de déplacement dossier/fichier.
 * TDD RED → les assertions vérifient uniquement la présence des éléments HTML.
 */
final class MoveModalWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $folderId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);

        $user   = $this->createWebUser('move-modal@example.com', 'secret123', 'MoveModal');
        $folder = new \App\Entity\Folder('Dossier MoveModal', $user);
        $this->em->persist($folder);
        $file = new \App\Entity\File('Fichier MoveModal.txt', 'text/plain', 1234, '2026/03/test.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $this->folderId = $folder->getId()->toRfc4122();

        $this->loginAs('move-modal@example.com', 'secret123');
    }

    public function testMoveFolderButtonExistsForEachFolder(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('[data-testid^="move-folder-btn-"]', 'Le bouton déplacer dossier doit exister');
    }

    public function testMoveFileButtonExistsForEachFile(): void
    {
        $this->client->request('GET', '/?folder=' . $this->folderId);
        $this->assertSelectorExists('[data-testid^="move-file-btn-"]', 'Le bouton déplacer fichier doit exister');
    }

    public function testGlobalMoveModalExistsInDOM(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('#move-modal', 'La modale globale doit exister');
        $this->assertSelectorExists('#move-modal.hidden', 'La modale doit être fermée par défaut');
    }

    public function testMoveModalHasFolderListArea(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('#move-target-list', 'La zone de liste des dossiers doit exister');
    }

    public function testMoveModalHasSubmitButton(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('#move-submit-btn', 'Le bouton confirmer doit exister');
    }

    public function testMoveModalHasTitle(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('#move-modal-title', 'Le titre de la modale doit exister');
    }

    public function testMoveModalClosedByDefault(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('#move-modal.hidden', 'La modale doit être fermée par défaut');
    }
}

