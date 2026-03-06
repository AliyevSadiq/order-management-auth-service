<?php

namespace App\Tests\Unit\Middleware;

use App\Middleware\JwtValidationMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class JwtValidationMiddlewareTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private JwtValidationMiddleware $middleware;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new JwtValidationMiddleware($this->logger);
    }

    public function testGetSubscribedEventsReturnsRequestEvent(): void
    {
        $events = JwtValidationMiddleware::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertSame(['onKernelRequest', 10], $events[KernelEvents::REQUEST]);
    }

    public function testPublicRouteRegisterIsSkipped(): void
    {
        $event = $this->createRequestEvent('/api/auth/register');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPublicRouteLoginIsSkipped(): void
    {
        $event = $this->createRequestEvent('/api/auth/login');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPublicRouteRefreshIsSkipped(): void
    {
        $event = $this->createRequestEvent('/api/auth/refresh');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPublicRouteApiDocIsSkipped(): void
    {
        $event = $this->createRequestEvent('/api/doc');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testNonApiRouteIsSkipped(): void
    {
        $event = $this->createRequestEvent('/health');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSubRequestIsSkipped(): void
    {
        $request = Request::create('/api/protected/resource');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testMissingAuthorizationHeaderReturns401(): void
    {
        $event = $this->createRequestEvent('/api/auth/profile');

        $this->logger->expects(self::once())->method('warning');

        $this->middleware->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());

        $content = json_decode($event->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $content['error']['code']);
        self::assertStringContainsString('Missing or invalid Authorization header', $content['error']['message']);
    }

    public function testInvalidAuthorizationHeaderFormatReturns401(): void
    {
        $event = $this->createRequestEvent('/api/auth/profile', 'Basic some-token');

        $this->middleware->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
    }

    public function testEmptyBearerTokenReturns401(): void
    {
        $event = $this->createRequestEvent('/api/auth/profile', 'Bearer ');

        $this->logger->expects(self::once())->method('warning');

        $this->middleware->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());

        $content = json_decode($event->getResponse()->getContent(), true);
        self::assertSame('JWT token is empty.', $content['error']['message']);
    }

    public function testValidBearerTokenPassesThrough(): void
    {
        $event = $this->createRequestEvent('/api/auth/profile', 'Bearer valid-jwt-token');

        $this->logger->expects(self::once())->method('debug');

        $this->middleware->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function createRequestEvent(string $path, ?string $authHeader = null): RequestEvent
    {
        $request = Request::create($path);

        if ($authHeader !== null) {
            $request->headers->set('Authorization', $authHeader);
        }

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
