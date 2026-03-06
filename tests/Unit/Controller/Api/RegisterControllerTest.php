<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\RegistrationServiceInterface;
use App\Controller\Api\RegisterController;
use App\Entity\User;
use App\Service\RequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterControllerTest extends TestCase
{
    private RegistrationServiceInterface&MockObject $registrationService;
    private ValidatorInterface&MockObject $validator;
    private RegisterController $controller;

    protected function setUp(): void
    {
        $this->registrationService = $this->createMock(RegistrationServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $requestValidator = new RequestValidator($this->validator);

        $this->controller = new RegisterController(
            $this->registrationService,
            $requestValidator,
        );

        $container = new \Symfony\Component\DependencyInjection\Container();
        $this->controller->setContainer($container);
    }

    public function testRegisterReturnsCreatedOnSuccess(): void
    {
        $user = $this->createUser();

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->registrationService->method('register')->willReturn($user);

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'StrongP@ss1',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]));

        $response = $this->controller->register($request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('User registered successfully.', $content['message']);
        self::assertSame('test@example.com', $content['user']['email']);
        self::assertSame('John', $content['user']['firstName']);
        self::assertSame('Doe', $content['user']['lastName']);
    }

    public function testRegisterReturnsBadRequestForInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json{');

        $response = $this->controller->register($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Invalid JSON payload.', $content['error']);
    }

    public function testRegisterReturnsValidationErrors(): void
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
            'firstName' => '',
            'lastName' => '',
        ]));

        $response = $this->controller->register($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Email is required.', $content['errors']['email']);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, Uuid::v4());

        return $user;
    }
}
