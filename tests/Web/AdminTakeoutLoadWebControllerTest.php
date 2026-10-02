<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\TakeoutDispatchLog;
use App\Entity\TakeoutImport;
use App\Service\Takeout\ServerLoadChecker;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * #528 : visibilité admin sur la charge serveur et les imports Takeout
 * différés (#522/#524) — load average courant face au seuil, imports
 * "scheduled" en attente et depuis quand, historique des cycles de dispatch.
 * Le mécanisme de dispatch lui-même n'est pas concerné.
 */
final class AdminTakeoutLoadWebControllerTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM takeout_dispatch_log');
        $conn->executeStatement('DELETE FROM takeout_imports');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();

        // Sans disableReboot(), le kernel redémarre entre le set() et la
        // requête et le service substitué serait écrasé (cf. AdminMailStatus).
        $this->client->disableReboot();

        // Mesure déterministe : le sys_getloadavg() réel de la machine de test
        // rendrait les assertions aléatoires.
        $container->set(ServerLoadChecker::class, new ServerLoadChecker(threshold: 8.0, loadAverageProvider: fn () => [1.5, 1.25, 1.0]));
    }

    private function loginAsAdmin(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
    }

    private function insertLog(string $outcome, \DateTimeImmutable $createdAt): void
    {
        $log = new TakeoutDispatchLog($outcome, [2.0, 2.0, 2.0], 8.0, 1, $outcome === TakeoutDispatchLog::OUTCOME_DISPATCHED ? 1 : 0);
        $this->em->persist($log);
        $this->em->flush();
        $this->em->getConnection()->executeStatement(
            'UPDATE takeout_dispatch_log SET created_at = ? WHERE id = ?',
            [$createdAt->format('Y-m-d H:i:s'), $log->getId()->toBinary()],
        );
        $this->em->clear();
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/takeout-load');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/takeout-load');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersEmptyStateWhenNothingScheduledAndNoHistory(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertStringContainsString('Aucun import en attente', $crawler->html());
        $this->assertStringContainsString('Aucun cycle enregistré', $crawler->html());
    }

    public function testDisplaysCurrentLoadAverageAndThreshold(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $text = $crawler->filter('.hc-takeout-load-current')->text();
        $this->assertStringContainsString('1,50', $text);
        $this->assertStringContainsString('1,25', $text);
        $this->assertStringContainsString('1,00', $text);
        $this->assertStringContainsString('8,00', $text);
        $this->assertStringContainsString('calme', $text);
    }

    public function testDisplaysScheduledImportWithOwnerAndWaitingSince(): void
    {
        $owner = $this->createWebUser('proprio@example.com', 'secret123', 'Proprio');
        $import = new TakeoutImport($owner);
        $import->markScheduled();
        $this->em->persist($import);
        $this->em->flush();
        $this->em->getConnection()->executeStatement(
            "UPDATE takeout_imports SET scheduled_at = '2026-10-01 08:30:00' WHERE id = ?",
            [$import->getId()->toBinary()],
        );
        $this->em->clear();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $text = $crawler->filter('.hc-takeout-load-scheduled')->text();
        $this->assertStringContainsString('proprio@example.com', $text);
        // 08:30 UTC → 10:30 à Paris (heure d'été)
        $this->assertStringContainsString('01/10/2026 10:30', $text);
    }

    public function testFallsBackToCreatedAtWhenScheduledAtMissing(): void
    {
        $owner = $this->createWebUser('proprio@example.com', 'secret123', 'Proprio');
        $import = new TakeoutImport($owner);
        $import->markScheduled();
        $this->em->persist($import);
        $this->em->flush();
        // Import passé en scheduled avant la colonne scheduled_at (#528).
        $this->em->getConnection()->executeStatement(
            "UPDATE takeout_imports SET scheduled_at = NULL, created_at = '2026-09-20 12:00:00' WHERE id = ?",
            [$import->getId()->toBinary()],
        );
        $this->em->clear();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $text = $crawler->filter('.hc-takeout-load-scheduled')->text();
        $this->assertStringContainsString('20/09/2026 14:00', $text);
        $this->assertStringContainsString('création', $text);
    }

    public function testIgnoresImportsThatAreNotScheduled(): void
    {
        $owner = $this->createWebUser('proprio@example.com', 'secret123', 'Proprio');
        $pending = new TakeoutImport($owner);
        $this->em->persist($pending);
        $this->em->flush();
        $this->em->clear();
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $this->assertStringContainsString('Aucun import en attente', $crawler->html());
    }

    public function testDisplaysDispatchHistoryAndCalmRateOverLast24Hours(): void
    {
        $now = new \DateTimeImmutable();
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DISPATCHED, $now->modify('-1 hour'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_IDLE, $now->modify('-2 hours'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-3 hours'));
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-4 hours'));
        // Hors fenêtre 24 h : visible dans l'historique, absent du taux.
        $this->insertLog(TakeoutDispatchLog::OUTCOME_DEFERRED, $now->modify('-3 days'));
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/takeout-load');

        $this->assertCount(5, $crawler->filter('.hc-takeout-load-history tbody tr'));
        $summary = $crawler->filter('.hc-takeout-load-summary')->text();
        // 2 cycles calmes (dispatched + idle) sur 4 → 50 %
        $this->assertStringContainsString('50', $summary);
        $this->assertStringContainsString('4', $summary);
    }

    public function testAdminLayoutHasNavLinkToTakeoutLoad(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/takeout-load');

        $this->assertSelectorExists('a[href="/admin/takeout-load"]');
    }
}
