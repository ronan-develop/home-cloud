<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * FlashMessage.html.twig déclarait data-controller="flash-close" mais
 * assets/controllers/flash_close_controller.js n'existait pas — Stimulus ne
 * pouvait jamais l'attacher. Le bouton de fermeture ne marchait que grâce à
 * un <script> inline redondant dans le même template, un doublon fragile du
 * même type que celui qui a cassé le bouton "Importer" du dashboard.
 */
final class FlashMessageComponentTest extends WebTestCase
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
        $user = new User('flash-test@example.com', 'FlashUser');
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => 'flash-test@example.com',
            'password' => 'secret123',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testFlashMessageUsesRealStimulusControllerNotInlineScript(): void
    {
        $this->login();

        // Déclenche un flash "error" via une route existante (email invalide).
        $crawler = $this->client->request('GET', '/invites');
        $token = $crawler->filter('form[action*="/invites/create"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/invites/create', [
            '_token' => $token,
            'email'  => 'pas-un-email',
        ]);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('data-controller="flash-close"', $html);
        $this->assertStringNotContainsString('el.querySelector(\'[data-action]\').onclick', $html);
    }
}
