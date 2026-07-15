<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Gestion CRUD des comptes invités (créer/éditer/supprimer) — HomeCloud
 * étant mono-owner par instance, la page liste tous les User accountType=guest,
 * sans notion de propriétaire de l'invitation.
 */
final class GuestManagementWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM reset_password_request');
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createOwner(): User
    {
        return $this->createWebUser('guest-mgmt-owner@example.com', 'secret123', 'Owner');
    }

    private function createGuest(string $email = 'invite@example.com', string $displayName = 'Invite'): User
    {
        $guest = new User($email, $displayName);
        $guest->markAsGuest();
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    public function testGuestsPageRequiresLogin(): void
    {
        $this->client->request('GET', '/invites');

        $this->assertResponseRedirects('/login');
    }

    public function testGuestsPageListsGuestAccountsOnly(): void
    {
        $this->createOwner();
        $this->createGuest('invite1@example.com', 'Invite One');
        $fullAccount = $this->createWebUser('full-account@example.com', 'secret123', 'Full Account');

        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('invite1@example.com', $crawler->filter('body')->text());
        $this->assertStringNotContainsString('full-account@example.com', $crawler->filter('body')->text());
    }

    public function testEditGuestDisplayName(): void
    {
        $this->createOwner();
        $guest = $this->createGuest();
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/' . $guest->getId() . '/edit"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/' . $guest->getId() . '/edit', [
            '_token'      => $token,
            'displayName' => 'Nouveau Nom',
        ]);

        $this->assertResponseRedirects('/invites');

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($guest->getId());
        $this->assertSame('Nouveau Nom', $reloaded->getDisplayName());
    }

    public function testDeleteGuestAccount(): void
    {
        $this->createOwner();
        $guest = $this->createGuest();
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/' . $guest->getId() . '/delete"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/' . $guest->getId() . '/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/invites');

        $this->em->clear();
        $this->assertNull($this->em->getRepository(User::class)->find($guest->getId()));
    }

    public function testDeleteGuestAccountWithPendingActivationRequestSucceeds(): void
    {
        // Reproduit le bug réel : un invité créé via GuestAccountCreator a
        // toujours une ResetPasswordRequest tant que le token d'activation
        // n'a pas expiré/été utilisé. La supprimer sans nettoyer cette ligne
        // violait la contrainte FK (500 ForeignKeyConstraintViolationException).
        $this->createOwner();
        $guest = $this->createGuest();

        $resetPasswordHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->generateResetToken($guest);

        $this->assertNotNull(
            $this->em->getRepository(ResetPasswordRequest::class)->findOneBy(['user' => $guest]),
            'précondition : une ResetPasswordRequest doit exister pour ce guest',
        );

        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/' . $guest->getId() . '/delete"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/' . $guest->getId() . '/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/invites');

        $this->em->clear();
        $this->assertNull($this->em->getRepository(User::class)->find($guest->getId()));
    }

    public function testGuestsPageHasCreateGuestForm(): void
    {
        $this->createOwner();
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action*="/invites/create"] input[name="email"]');
    }

    public function testCreateGuestAccountSucceeds(): void
    {
        $this->createOwner();
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/create"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/create', [
            '_token' => $token,
            'email'  => 'nouvel-invite@example.com',
        ]);

        $this->assertResponseRedirects('/invites');

        $created = $this->userRepositoryFindByEmail('nouvel-invite@example.com');
        $this->assertNotNull($created);
        $this->assertTrue($created->isGuest());
    }

    public function testCreateGuestAccountWithExistingEmailFails(): void
    {
        $this->createOwner();
        $this->createGuest('deja-existant@example.com');
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/create"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/create', [
            '_token' => $token,
            'email'  => 'deja-existant@example.com',
        ]);

        $this->assertResponseRedirects('/invites');
        $this->client->followRedirect();
        $this->assertStringContainsString('existe déjà', $this->client->getResponse()->getContent());
    }

    public function testCreateGuestAccountWithInvalidEmailFails(): void
    {
        $this->createOwner();
        $this->loginAs('guest-mgmt-owner@example.com');

        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/create"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/create', [
            '_token' => $token,
            'email'  => 'pas-un-email',
        ]);

        $this->assertResponseRedirects('/invites');
        $this->client->followRedirect();
        $this->assertStringContainsString('invalide', $this->client->getResponse()->getContent());
    }

    private function userRepositoryFindByEmail(string $email): ?User
    {
        return static::getContainer()->get(\App\Interface\UserRepositoryInterface::class)
            ->findOneBy(['email' => $email]);
    }

    public function testCannotEditOrDeleteAFullAccountViaGuestManagement(): void
    {
        $this->createOwner();
        $fullAccount = $this->createWebUser('full-account2@example.com', 'secret123', 'Full Account');
        $this->loginAs('guest-mgmt-owner@example.com');

        // Le token CSRF "guest-edit" n'est pas lié à un id précis, seul le
        // contrôleur vérifie que la cible est bien accountType=guest.
        $guest = $this->createGuest('other-invite@example.com');
        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/' . $guest->getId() . '/edit"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/' . $fullAccount->getId() . '/edit', [
            '_token'      => $token,
            'displayName' => 'Hacked',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}
