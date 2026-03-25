<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\AuthenticationFailureListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final class AuthenticationFailureListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private AuthenticationFailureListener $listener;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new AuthenticationFailureListener($this->logger);
    }

    private function buildEvent(string $email = 'test@example.com', string $userAgent = 'TestAgent/1.0'): LoginFailureEvent
    {
        $request = Request::create(
            '/api/v1/auth/login',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_USER_AGENT' => $userAgent,
                'REMOTE_ADDR' => '192.168.1.1',
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['email' => $email, 'password' => 'wrongpassword'])
        );

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $exception = new BadCredentialsException();

        return new LoginFailureEvent($exception, $authenticator, $request, null, 'login');
    }

    public function testLogsWarningOnFailedLogin(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Authentication failure'),
                $this->callback(fn (array $context) => true)
            );

        ($this->listener)($this->buildEvent());
    }

    public function testLogsEmailInContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => 'attacker@evil.com' === $context['email'])
            );

        ($this->listener)($this->buildEvent(email: 'attacker@evil.com'));
    }

    public function testLogsIpInContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => '192.168.1.1' === $context['ip'])
            );

        ($this->listener)($this->buildEvent());
    }

    public function testLogsUserAgentInContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => 'CustomBot/2.0' === $context['user_agent'])
            );

        ($this->listener)($this->buildEvent(userAgent: 'CustomBot/2.0'));
    }
}
