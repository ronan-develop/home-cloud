<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Persistence\ObjectManager;

class AbstractApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Chargement programmatique des fixtures dans le même process
        self::bootKernel();
        $container = static::getContainer();
        /** @var ObjectManager $em */
        $em = $container->get('doctrine')->getManager();
        $loader = new Loader();
        // Ajoute ici toutes tes fixtures explicitement
        $loader->addFixture(new \App\DataFixtures\PrivateSpaceFixture());
        $loader->addFixture(new \App\DataFixtures\UserFixture($container->get('security.user_password_hasher')));
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->purge();
        $executor->execute($loader->getFixtures());
    }
    protected function getJwtToken(string $email = 'demo@homecloud.local', string $password = 'test'): string
    {
        $response = static::createClient()->request('POST', '/api/login_check', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);
        $data = $response->toArray(false);
        if (!isset($data['token'])) {
            fwrite(STDERR, "[DEBUG] JWT login response: " . json_encode($data) . "\n");
            throw new \RuntimeException('JWT token not found in response: ' . json_encode($data));
        }
        return $data['token'];
    }
}
