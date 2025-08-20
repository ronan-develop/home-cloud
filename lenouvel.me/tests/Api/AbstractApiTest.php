<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\Process\Process;

class AbstractApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Recharge les fixtures via la console Symfony avec purge par suppression
        $process = new Process([
            'php',
            'bin/console',
            'doctrine:fixtures:load',
            '--env=test',
            '--no-interaction',
            '--purger=delete'
        ]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Fixtures loading failed: ' . $process->getErrorOutput());
        }
    }
}
