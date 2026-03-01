<?php

namespace App\Tests\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class ResetPasswordApiTest extends WebTestCase
{


    public function testRequestResetPassword(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'reset-test@homecloud.local']);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
        }
        $user = new User('reset-test@homecloud.local', 'Reset Test');
        $user->setPassword('dummy');
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/api/request-reset-password', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'reset-test@homecloud.local'
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            json_encode(['message' => 'Si un compte existe, un email a été envoyé.']),
            $client->getResponse()->getContent()
        );
    }
}
