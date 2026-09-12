<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\ActivityTrackerSubscriber;
use App\Interface\ActivityTrackerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ActivityTrackerSubscriberTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testSubscribesToKernelRequestWithLowPriority(): void
    {
        $events = ActivityTrackerSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertSame(['onKernelRequest', -100], $events[KernelEvents::REQUEST]);
    }

    public function testRecordsActivityWhenAuthenticatedAsAppUser(): void
    {
        $user = $this->createStub(User::class);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $activityTracker = $this->createMock(ActivityTrackerInterface::class);
        $activityTracker->expects(self::once())->method('recordActivity');

        $subscriber = new ActivityTrackerSubscriber($activityTracker, $tokenStorage);
        $subscriber->onKernelRequest($this->createMainRequestEvent());
    }

    public function testDoesNothingWhenNoToken(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $activityTracker = $this->createMock(ActivityTrackerInterface::class);
        $activityTracker->expects(self::never())->method('recordActivity');

        $subscriber = new ActivityTrackerSubscriber($activityTracker, $tokenStorage);
        $subscriber->onKernelRequest($this->createMainRequestEvent());
    }

    public function testDoesNothingWhenTokenUserIsNotAppUser(): void
    {
        // cas BroadcastTokenAuthenticator (#283) : token authentifié mais InMemoryUser, pas notre entité
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(new InMemoryUser('broadcast', null));

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $activityTracker = $this->createMock(ActivityTrackerInterface::class);
        $activityTracker->expects(self::never())->method('recordActivity');

        $subscriber = new ActivityTrackerSubscriber($activityTracker, $tokenStorage);
        $subscriber->onKernelRequest($this->createMainRequestEvent());
    }

    public function testDoesNothingOnSubRequest(): void
    {
        $user = $this->createStub(User::class);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $activityTracker = $this->createMock(ActivityTrackerInterface::class);
        $activityTracker->expects(self::never())->method('recordActivity');

        $subscriber = new ActivityTrackerSubscriber($activityTracker, $tokenStorage);
        $event = new RequestEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);
    }

    private function createMainRequestEvent(): RequestEvent
    {
        return new RequestEvent($this->kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
    }
}
