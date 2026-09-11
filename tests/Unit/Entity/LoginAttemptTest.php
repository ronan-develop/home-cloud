<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LoginAttempt;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class LoginAttemptTest extends TestCase
{
    public function testConstructorInitializesUuidV7(): void
    {
        $attempt = new LoginAttempt(hash('sha256', 'test@example.com'), '127.0.0.1', 'TestAgent/1.0');

        $this->assertInstanceOf(UuidV7::class, $attempt->getId());
    }

    public function testConstructorStoresGivenValues(): void
    {
        $emailHash = hash('sha256', 'test@example.com');

        $attempt = new LoginAttempt($emailHash, '127.0.0.1', 'TestAgent/1.0');

        $this->assertSame($emailHash, $attempt->getEmailHash());
        $this->assertSame('127.0.0.1', $attempt->getIp());
        $this->assertSame('TestAgent/1.0', $attempt->getUserAgent());
    }

    public function testConstructorSetsCreatedAtToNow(): void
    {
        $before = new \DateTimeImmutable();

        $attempt = new LoginAttempt(hash('sha256', 'test@example.com'), '127.0.0.1', 'TestAgent/1.0');

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $attempt->getCreatedAt());
        $this->assertLessThanOrEqual($after, $attempt->getCreatedAt());
    }

    public function testAcceptsNullIpAndUserAgent(): void
    {
        $attempt = new LoginAttempt(hash('sha256', 'test@example.com'), null, null);

        $this->assertNull($attempt->getIp());
        $this->assertNull($attempt->getUserAgent());
    }
}
