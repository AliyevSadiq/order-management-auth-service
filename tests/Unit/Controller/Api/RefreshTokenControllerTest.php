<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\TokenServiceInterface;
use App\Controller\Api\RefreshTokenController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RefreshTokenControllerTest extends TestCase
{
    private TokenServiceInterface&MockObject $tokenService;
    private RefreshTokenController $controller;

    protected function setUp(): void
    {
        $this->tokenService = $this->createMock(TokenServiceInterface::class);
        $this->controller = new RefreshTokenController($this->tokenService);

        $container = new \Symfony\Component\DependencyInjection\Container();
        $this->controller->setContainer($container);
    }

    public function testRefreshReturnsTokensOnSuccess(): void
    {
        $tokenData = [
            'access_token' => 'new-jwt',
            'refresh_token' => 'new-refresh',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        $this->tokenService->method('refreshToken')
            ->with('old-refresh-token')
            ->willReturn($tokenData);

        $request = new Request([], [], [], [], [], [], json_encode([
            'refresh_token' => 'old-refresh-token',
        ]));

        $response = $this->controller->refresh($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('new-jwt', $content['access_token']);
        self::assertSame('new-refresh', $content['refresh_token']);
    }

    public function testRefreshReturnsBadRequestWhenJsonIsNull(): void
    {
        $request = new Request([], [], [], [], [], [], 'invalid-json');

        $response = $this->controller->refresh($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Refresh token is required.', $content['error']);
    }

    public function testRefreshReturnsBadRequestWhenRefreshTokenMissing(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'some_other_field' => 'value',
        ]));

        $response = $this->controller->refresh($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Refresh token is required.', $content['error']);
    }
}
