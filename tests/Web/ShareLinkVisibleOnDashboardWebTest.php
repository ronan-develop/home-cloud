<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Reproduit le parcours réel signalé en usage : autoriser le partage par
 * lien, créer le lien via l'UI (POST /share-link-create, pas une insertion
 * directe en base), puis vérifier qu'il apparaît sur /partages.
 */
final class ShareLinkVisibleOnDashboardWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createOwner(): User
    {
        return $this->createWebUser('dashboard-owner@example.com', 'secret123', 'Owner');
    }

    public function testLinkCreatedViaUiAppearsOnSharesDashboard(): void
    {
        $owner = $this->createOwner();
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        $this->loginAs('dashboard-owner@example.com');

        // Étape 1 — autoriser le partage par lien (private -> link_allowed)
        $crawler = $this->client->request('GET', '/explorer');
        $visibilityToken = $crawler->filter('form[action*="/resource-visibility-update"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/resource-visibility-update', [
            '_token'       => $visibilityToken,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
            'visibility'   => 'link_allowed',
        ]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();

        // Étape 2 — créer le lien via le vrai contrôleur (pas une insertion directe)
        $crawler = $this->client->request('GET', '/explorer');
        $linkToken = $crawler->filter('form[action*="/share-link-create"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/share-link-create', [
            '_token'       => $linkToken,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
        ]);
        $this->assertResponseRedirects();

        // Étape 3 — vérifier la présence sur le dashboard
        $crawler = $this->client->request('GET', '/partages');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-link-row"]');
        $this->assertStringContainsString('photo.jpg', $crawler->filter('[data-testid="share-link-row"]')->text());
    }
}
