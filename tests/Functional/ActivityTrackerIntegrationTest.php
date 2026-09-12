<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Interface\ActivityTrackerInterface;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que la détection d'activité (#422) se déclenche bien de bout en
 * bout via une vraie requête HTTP authentifiée, et pas seulement dans les
 * tests unitaires isolés du service et du subscriber.
 */
final class ActivityTrackerIntegrationTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private KernelBrowser $client;
    private string $activityFilePath;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var string $projectDir */
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $this->activityFilePath = $projectDir . '/var/last-activity.txt';
        @unlink($this->activityFilePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->activityFilePath);
        parent::tearDown();
    }

    public function testAuthenticatedRequestRecordsActivity(): void
    {
        $this->createWebUser();
        $this->loginAs();

        self::assertFileExists($this->activityFilePath);

        $tracker = static::getContainer()->get(ActivityTrackerInterface::class);
        $lastActivity = $tracker->getLastActivityAt();

        self::assertNotNull($lastActivity);
        self::assertGreaterThan((new \DateTimeImmutable('-1 minute')), $lastActivity);
    }

    public function testAnonymousRequestDoesNotRecordActivity(): void
    {
        $this->client->request('GET', '/login');

        self::assertFileDoesNotExist($this->activityFilePath);
    }
}
