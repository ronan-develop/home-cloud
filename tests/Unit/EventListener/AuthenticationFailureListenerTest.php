<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\LoginAttempt;
use App\EventListener\AuthenticationFailureListener;
use App\Repository\LoginAttemptRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

final class AuthenticationFailureListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private LoginAttemptRepository $loginAttemptRepository;
    private AuthenticationFailureListener $listener;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — un createMock()
        // créé ici puis jamais vérifié (même réassigné ensuite) déclenche
        // quand même le warning PHPUnit, car il est tracké dès sa création (#454).
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->loginAttemptRepository = $this->createStub(LoginAttemptRepository::class);
        $this->rebuild();
    }

    private function rebuild(): void
    {
        $this->listener = new AuthenticationFailureListener($this->logger, $this->loginAttemptRepository);
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

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $exception = new BadCredentialsException();

        return new LoginFailureEvent($exception, $authenticator, $request, null, 'login');
    }

    public function testLogsWarningOnFailedLogin(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Authentication failure'),
                $this->callback(fn (array $context) => true)
            );
        $this->rebuild();

        ($this->listener)($this->buildEvent());
    }

    public function testLogsHashedEmailInContext(): void
    {
        $expectedHash = substr(hash('sha256', 'attacker@evil.com'), 0, 12);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => $expectedHash === $context['email_hash'])
            );
        $this->rebuild();

        ($this->listener)($this->buildEvent(email: 'attacker@evil.com'));
    }

    public function testDoesNotLogPlainEmail(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => !\in_array('attacker@evil.com', $context, true)
                    && !\array_key_exists('email', $context))
            );
        $this->rebuild();

        ($this->listener)($this->buildEvent(email: 'attacker@evil.com'));
    }

    public function testLogsIpInContext(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => '192.168.1.1' === $context['ip'])
            );
        $this->rebuild();

        ($this->listener)($this->buildEvent());
    }

    public function testLogsUserAgentInContext(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn (array $context) => 'CustomBot/2.0' === $context['user_agent'])
            );
        $this->rebuild();

        ($this->listener)($this->buildEvent(userAgent: 'CustomBot/2.0'));
    }

    public function testPersistsLoginAttemptWithFullEmailHash(): void
    {
        $expectedHash = hash('sha256', 'attacker@evil.com');

        $this->loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);
        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (LoginAttempt $attempt) => $expectedHash === $attempt->getEmailHash()
                    && '192.168.1.1' === $attempt->getIp()
                    && 'TestAgent/1.0' === $attempt->getUserAgent()
            ));
        $this->rebuild();

        ($this->listener)($this->buildEvent(email: 'attacker@evil.com'));
    }

    public function testDoesNotBreakOnPersistenceFailure(): void
    {
        $this->loginAttemptRepository
            ->method('save')
            ->willThrowException(new \RuntimeException('DB down'));

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->expects($this->exactly(2))
            ->method('warning');
        $this->rebuild();

        ($this->listener)($this->buildEvent());
    }
}
