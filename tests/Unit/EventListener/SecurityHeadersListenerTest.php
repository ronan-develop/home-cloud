<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    private function buildEvent(string $path = '/api/v1/users'): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);
        $response = new Response();

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    public function testCommonSecurityHeadersAreAlwaysPresent(): void
    {
        $event = $this->buildEvent();
        (new SecurityHeadersListener('test'))($event);

        $headers = $event->getResponse()->headers;
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $headers->get('X-Frame-Options'));
        $this->assertSame('no-referrer', $headers->get('Referrer-Policy'));
    }

    public function testCspHeaderIsSetOnApiRoutes(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('test'))($event);

        $this->assertSame("default-src 'none'", $event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testCspHeaderIsNotSetOnApiDocs(): void
    {
        $event = $this->buildEvent('/api/docs');
        (new SecurityHeadersListener('test'))($event);

        $this->assertNull($event->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testHstsHeaderIsPresentInProd(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('prod'))($event);

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $event->getResponse()->headers->get('Strict-Transport-Security')
        );
    }

    public function testHstsHeaderIsAbsentInDev(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('dev'))($event);

        $this->assertNull($event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testHstsHeaderIsAbsentInTest(): void
    {
        $event = $this->buildEvent('/api/v1/users');
        (new SecurityHeadersListener('test'))($event);

        $this->assertNull($event->getResponse()->headers->get('Strict-Transport-Security'));
    }
}
