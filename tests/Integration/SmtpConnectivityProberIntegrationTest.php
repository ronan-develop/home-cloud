<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\SmtpConnectivityProber;
use PHPUnit\Framework\TestCase;

final class SmtpConnectivityProberIntegrationTest extends TestCase
{
    public function testThrowsWhenConnectionIsRefused(): void
    {
        $prober = new SmtpConnectivityProber();

        $this->expectException(\Throwable::class);
        $prober->probe('smtp://127.0.0.1:1', 1.0);
    }
}
