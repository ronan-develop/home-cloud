<?php

namespace App\Tests\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResetPasswordSubmitApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'reset-test@homecloud.local']);
        if (!$existing) {
            $user = new User('reset-test@homecloud.local', 'Reset Test');
            $ref = new \ReflectionProperty($user, 'id');
            $ref->setValue($user, \Symfony\Component\Uid\Uuid::v4());
            $user->setPassword('TestPassword123!');
            $em->persist($user);
            $em->flush();
        }
        static::ensureKernelShutdown();
    }

    public function testSubmitNewPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Préparer un utilisateur de test
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'reset-test@homecloud.local']);
        $this->assertNotNull($user, 'User de test inexistant');

        // Générer un token de reset (via l'API ou le helper directement)
        $resetPasswordHelper = static::getContainer()->get('SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface');
        $resetToken = $resetPasswordHelper->generateResetToken($user)->getToken();

        // Appel API pour soumettre le nouveau mot de passe
        $client->request(
            'POST',
            '/api/reset-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'token' => $resetToken,
                'password' => 'NouveauPassword123!'
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('mot de passe a été réinitialisé', $data['message']);
    }
}
