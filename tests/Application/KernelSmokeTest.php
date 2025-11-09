<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class KernelSmokeTest extends WebTestCase
{
    public function testHomePageAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertTrue(
            in_array($client->getResponse()->getStatusCode(), [200, 302]),
            'Le kernel Symfony répond (code 200 ou 302)'
        );
    }
}
