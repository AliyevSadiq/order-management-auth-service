<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\LoginServiceInterface;
use App\Controller\Api\LoginController;
use App\Service\RequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LoginControllerTest extends TestCase
{
    private LoginServiceInterface&MockObject $loginService;
    private ValidatorInterface&MockObject $validator;
    private LoginController $controller;

    protected function setUp(): void
    {
        $this->loginService = $this->createMock(LoginServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $requestValidator = new RequestValidator($this->validator);

        $this->controller = new LoginController(
            $this->loginService,
            $requestValidator,
        );

        $container = new \Symfony\Component\DependencyInjection\Container();
        $this->controller->setContainer($container);
    }

    public function testLoginReturnsTokensOnSuccess(): void
    {
        $tokenData = [
            'access_token' => 'jwt-token',
            'refresh_token' => 'refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => ['id' => '1', 'email' => 'test@example.com'],
        ];

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->loginService->method('login')->willReturn($tokenData);

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $response = $this->controller->login($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('jwt-token', $content['access_token']);
    }

    public function testLoginReturnsBadRequestForInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json{');

        $response = $this->controller->login($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Invalid JSON payload.', $content['error']);
    }

    public function testLoginReturnsValidationErrorsWhenValidationFails(): void
    {
        $violation = new ConstraintViolation(
            'Email is required.',
            null,
            [],
            null,
            'email',
            '',
        );

        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => '',
            'password' => '',
        ]));

        $response = $this->controller->login($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Email is required.', $content['errors']['email']);
    }

    public function testLoginHandlesMissingFieldsGracefully(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->loginService->method('login')->willReturn(['access_token' => 'token']);

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->login($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
