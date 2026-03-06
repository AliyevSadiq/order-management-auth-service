<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\TokenServiceInterface;
use App\Controller\Api\LogoutController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogoutControllerTest extends TestCase
{
    private TokenServiceInterface&MockObject $tokenService;
    private LogoutController $controller;

    protected function setUp(): void
    {
        $this->tokenService = $this->createMock(TokenServiceInterface::class);
        $this->controller = new LogoutController($this->tokenService);

        $container = new \Symfony\Component\DependencyInjection\Container();
        $this->controller->setContainer($container);
    }

    public function testLogoutRevokesRefreshTokenAndReturnsNoContent(): void
    {
        $this->tokenService->expects(self::once())
            ->method('logout')
            ->with('refresh-token-value');

        $request = new Request([], [], [], [], [], [], json_encode([
            'refresh_token' => 'refresh-token-value',
        ]));

        $response = $this->controller->logout($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLogoutWithoutRefreshTokenDoesNotCallService(): void
    {
        $this->tokenService->expects(self::never())->method('logout');

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->logout($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLogoutWithNullBodyDoesNotCallService(): void
    {
        $this->tokenService->expects(self::never())->method('logout');

        $request = new Request([], [], [], [], [], [], 'invalid-json');

        $response = $this->controller->logout($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
