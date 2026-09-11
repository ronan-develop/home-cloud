<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MailerConnectivityChecker;
use App\Service\SmtpConnectivityProberInterface;
use PHPUnit\Framework\TestCase;

final class MailerConnectivityCheckerTest extends TestCase
{
    public function testReturnsNotConfiguredWhenDsnIsNull(): void
    {
        $prober = $this->createStub(SmtpConnectivityProberInterface::class);
        $checker = new MailerConnectivityChecker('null://null', $prober);

        $result = $checker->check();

        $this->assertFalse($result->isConfigured);
        $this->assertNull($result->isReachable);
        $this->assertNull($result->errorMessage);
    }

    public function testDoesNotCallProberWhenDsnIsNull(): void
    {
        $prober = $this->createMock(SmtpConnectivityProberInterface::class);
        $prober->expects($this->never())->method('probe');

        $checker = new MailerConnectivityChecker('null://null', $prober);
        $checker->check();
    }

    public function testReturnsReachableWhenProberSucceeds(): void
    {
        $prober = $this->createStub(SmtpConnectivityProberInterface::class);

        $checker = new MailerConnectivityChecker('smtp://user:pass@example.test:465', $prober);
        $result = $checker->check();

        $this->assertTrue($result->isConfigured);
        $this->assertTrue($result->isReachable);
        $this->assertNull($result->errorMessage);
    }

    public function testReturnsUnreachableWithErrorMessageWhenProberThrows(): void
    {
        $prober = $this->createStub(SmtpConnectivityProberInterface::class);
        $prober->method('probe')->willThrowException(new \RuntimeException('Connection could not be established'));

        $checker = new MailerConnectivityChecker('smtp://user:pass@example.test:465', $prober);
        $result = $checker->check();

        $this->assertTrue($result->isConfigured);
        $this->assertFalse($result->isReachable);
        $this->assertSame('Connection could not be established', $result->errorMessage);
    }

    public function testPassesConfiguredTimeoutToProber(): void
    {
        $prober = $this->createMock(SmtpConnectivityProberInterface::class);
        $prober->expects($this->once())
            ->method('probe')
            ->with($this->anything(), 3.0);

        $checker = new MailerConnectivityChecker('smtp://user:pass@example.test:465', $prober);
        $checker->check();
    }
}
