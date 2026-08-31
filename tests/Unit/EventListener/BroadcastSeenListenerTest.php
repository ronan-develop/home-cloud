<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\BroadcastMessage;
use App\Entity\User;
use App\EventListener\BroadcastSeenListener;
use App\Interface\BroadcastMessageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * TDD RED → GREEN : marquage "vu" du broadcast in-app (#361, suite de
 * #283). Sur KernelEvents::RESPONSE (après le rendu Twig, qui a donc encore
 * vu l'état non-lu) — jamais sur /api ou /internal.
 */
final class BroadcastSeenListenerTest extends TestCase
{
    private function buildEvent(string $path, bool $mainRequest = true, int $statusCode = 200): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);
        $response = new Response(status: $statusCode);

        return new ResponseEvent(
            $kernel,
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response,
        );
    }

    private function repositoryWithLatest(?BroadcastMessage $message): BroadcastMessageRepositoryInterface
    {
        $stub = $this->createStub(BroadcastMessageRepositoryInterface::class);
        $stub->method('findLatest')->willReturn($message);

        return $stub;
    }

    public function testMarksMessageAsSeenAfterRenderingAWebPage(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest($message), $em);
        $listener($this->buildEvent('/'));

        $this->assertNotNull($user->getLastBroadcastSeenAt());
    }

    public function testDoesNotMarkAsSeenOnApiRoutes(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest($message), $em);
        $listener($this->buildEvent('/api/v1/files'));

        $this->assertNull($user->getLastBroadcastSeenAt());
    }

    public function testDoesNotMarkAsSeenOnInternalBroadcastRoute(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest($message), $em);
        $listener($this->buildEvent('/internal/broadcast'));

        $this->assertNull($user->getLastBroadcastSeenAt());
    }

    public function testDoesNotTouchAnonymousRequests(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest(new BroadcastMessage('S', 'B')), $em);
        $listener($this->buildEvent('/login'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenAlreadySeen(): void
    {
        $message = new BroadcastMessage('Sujet', 'Corps');
        $user = new User('user@example.com', 'User');
        $user->setLastBroadcastSeenAt(new \DateTimeImmutable('+1 day'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest($message), $em);
        $listener($this->buildEvent('/'));
    }

    public function testDoesNothingWhenNoBroadcastMessageExists(): void
    {
        $user = new User('user@example.com', 'User');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest(null), $em);
        $listener($this->buildEvent('/'));
    }

    public function testDoesNothingOnSubRequests(): void
    {
        $user = new User('user@example.com', 'User');
        $message = new BroadcastMessage('Sujet', 'Corps');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $listener = new BroadcastSeenListener($security, $this->repositoryWithLatest($message), $em);
        $listener($this->buildEvent('/', mainRequest: false));
    }
}
