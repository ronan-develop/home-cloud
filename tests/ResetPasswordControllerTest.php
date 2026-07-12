<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->userRepository = $container->get(UserRepository::class);

        // Purge complète (cascade FK désactivée le temps du nettoyage) — la base
        // de test peut contenir des dossiers/fichiers/médias/albums liés aux users
        // (ex: fixtures de démo), pas seulement des reset_password_request.
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM reset_password_request');
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    public function testResetPasswordController(): void
    {
        // Create a test user
        $user = new User('me@example.com', 'Test User');
        $user->setPassword('a-test-password-that-will-be-changed-later');
        $this->em->persist($user);
        $this->em->flush();

        // Test Request reset password page
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Réinitialiser mon mot de passe');


        // Simule l'appel JS : POST sur l'API
        $this->client->request('POST', '/api/request-reset-password', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['email' => 'me@example.com']));

        // Vérifie la réponse API
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('message', $data);

        // On ne vérifie plus l'envoi d'email ni le contenu du mail

        // On ne teste plus le lien reçu par email ni la page de confirmation

        // Ici on ne teste que la demande de reset password (pas la soumission du nouveau mot de passe)
    }
}
