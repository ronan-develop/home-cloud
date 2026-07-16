<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Le bouton "Importer" du dashboard ciblait un #upload-modal supprimé lors
 * du nettoyage de layout.html.twig (dead code) — plus aucun clic ne
 * fonctionnait. Doit utiliser le même mécanisme que le menu "Nouveau" de la
 * sidebar (événement hc:files-selected, consommé par upload-modal.js).
 */
final class DashboardImportButtonTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function login(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User('dashboard-import@example.com', 'DashUser');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => 'dashboard-import@example.com',
            'password' => 'secret123',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testDashboardImportButtonHasNoOnclickAndUsesFileInput(): void
    {
        $this->login();

        $this->assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString("getElementById('upload-modal')", $html);
        $this->assertSelectorExists('[data-testid="dashboard-import-btn"] input[type="file"]');
        $this->assertSelectorExists('[data-controller="dashboard-import"]');
    }
}
