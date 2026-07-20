<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * #290 : page changelog listant les grands thèmes de l'historique du projet
 * (date + titre + lien GitHub), sans détail technique.
 */
final class ChangelogControllerTest extends WebTestCase
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

    private function login(string $email = 'test@example.com', string $password = 'pwd12345'): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testChangelogPageIsAccessibleToAuthenticatedUser(): void
    {
        $this->login();

        $this->client->request('GET', '/changelog');

        $this->assertResponseIsSuccessful();
    }

    public function testChangelogPageListsEntriesWithGitHubLinks(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/changelog');

        $rows = $crawler->filter('table tbody tr');
        $this->assertGreaterThan(0, $rows->count(), 'Le changelog doit afficher au moins une entrée.');

        $githubLinks = $crawler->filter('table tbody tr a[href^="https://github.com/"]');
        $this->assertSame($rows->count(), $githubLinks->count(), 'Chaque entrée doit avoir un lien GitHub associé.');
    }

    public function testChangelogPageRequiresAuthentication(): void
    {
        $this->client->request('GET', '/changelog');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Les dates viennent de l'API GitHub au format ISO (Y-m-d) — affichées
     * en français (jj/mm/aaaa) plutôt que le format ISO brut.
     */
    public function testChangelogPageDisplaysDatesInFrenchFormat(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/changelog');

        $firstRowText = $crawler->filter('table tbody tr')->first()->text();

        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $firstRowText);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $firstRowText);
    }

    /**
     * #290 (retour utilisateur) : l'historique complet doit être disponible,
     * mais paginé plutôt qu'affiché en une seule page géante. FakeChangelogFetcher
     * fournit 45 entrées de test — la page 1 doit en afficher un sous-ensemble.
     */
    public function testChangelogPageIsPaginated(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/changelog');

        $rows = $crawler->filter('table tbody tr');
        $this->assertLessThan(45, $rows->count(), 'La page 1 ne doit pas afficher les 45 entrées d\'un coup.');

        $pagination = $crawler->filter('[data-testid="changelog-pagination"]');
        $this->assertGreaterThan(0, $pagination->count(), 'Des contrôles de pagination doivent être visibles.');
    }

    public function testChangelogPageSecondPageShowsDifferentEntries(): void
    {
        $this->login();

        $crawlerPage1 = $this->client->request('GET', '/changelog');
        $firstEntryPage1 = trim($crawlerPage1->filter('table tbody tr')->first()->text());

        $crawlerPage2 = $this->client->request('GET', '/changelog?page=2');
        $firstEntryPage2 = trim($crawlerPage2->filter('table tbody tr')->first()->text());

        $this->assertResponseIsSuccessful();
        $this->assertNotSame($firstEntryPage1, $firstEntryPage2, 'La page 2 doit afficher des entrées différentes de la page 1.');
    }
}
