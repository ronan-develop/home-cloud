<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * #500 : les refus d'accès/ressources introuvables volontaires (403/404),
 * déclenchés intentionnellement par les tests fonctionnels, ne doivent pas
 * polluer les logs — seules les erreurs inattendues (500) doivent apparaître.
 */
final class MonologTestFilterTest extends WebTestCase
{
    use WebFixturesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $logFile;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->logFile = static::getContainer()->getParameter('kernel.logs_dir') . '/test.log';
        @unlink($this->logFile);
    }

    public function testForbiddenAccessIsNotLogged(): void
    {
        // Utilisateur authentifié mais non whitelisté admin → 403 volontaire (garde applicative).
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/login-attempts');

        self::assertResponseStatusCodeSame(403);
        self::assertFileDoesNotContainErrorLevel($this->logFile);
    }

    public function testNotFoundIsNotLogged(): void
    {
        $this->client->request('GET', '/this-route-does-not-exist-500');

        self::assertResponseStatusCodeSame(404);
        self::assertFileDoesNotContainErrorLevel($this->logFile);
    }

    public function testUnexpectedErrorIsStillLogged(): void
    {
        /** @var LoggerInterface $logger */
        $logger = static::getContainer()->get('monolog.logger.request');
        $logger->error('Erreur inattendue de test (#500)');

        self::assertFileExists($this->logFile);
        self::assertStringContainsString('.ERROR: Erreur inattendue de test (#500)', file_get_contents($this->logFile) ?: '');
    }

    private static function assertFileDoesNotContainErrorLevel(string $logFile): void
    {
        if (!is_file($logFile)) {
            self::assertTrue(true);

            return;
        }

        $content = file_get_contents($logFile);
        self::assertStringNotContainsString('.ERROR:', $content ?: '');
    }
}
