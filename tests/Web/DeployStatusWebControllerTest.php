<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : endpoint de polling exposant l'imminence d'un
 * déploiement (#422 étape 3/3) — consulté par la popup front avec compte à
 * rebours. Accessible à tout utilisateur authentifié (pas réservé à
 * l'admin), puisque n'importe quel user actif doit être averti.
 */
final class DeployStatusWebControllerTest extends WebTestCase
{
    use WebFixturesTrait;

    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $filePath;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->filePath = static::getContainer()->getParameter('app.deploy_imminent.file_path');
        @unlink($this->filePath);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);
        parent::tearDown();
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/deploy-status');

        $this->assertResponseRedirects('/login');
    }

    public function testReturnsImminentFalseWhenNoDeploymentPending(): void
    {
        $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs('user@example.com');

        $this->client->request('GET', '/deploy-status');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['imminent']);
        $this->assertArrayNotHasKey('etaSeconds', $data);
    }

    public function testReturnsImminentTrueWithEtaWhenDeploymentPending(): void
    {
        file_put_contents($this->filePath, (string) time());
        $this->createWebUser('user@example.com', 'secret123', 'User');
        $this->loginAs('user@example.com');

        $this->client->request('GET', '/deploy-status');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['imminent']);
        $this->assertArrayHasKey('etaSeconds', $data);
        $this->assertGreaterThan(0, $data['etaSeconds']);
    }
}
