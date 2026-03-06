<?php

namespace App\Tests\Unit\Service;

use App\Contract\PasswordHasherServiceInterface;
use App\DTO\RegisterRequest;
use App\Entity\User;
use App\Message\UserRegistered;
use App\Repository\UserRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RegistrationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserRepository&MockObject $userRepository;
    private PasswordHasherServiceInterface&MockObject $passwordHasherService;
    private MessageBusInterface&MockObject $messageBus;
    private RegistrationService $registrationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasherService = $this->createMock(PasswordHasherServiceInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->registrationService = new RegistrationService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordHasherService,
            $this->messageBus,
        );
    }

    public function testRegisterSuccessfully(): void
    {
        $request = new RegisterRequest(
            email: 'new@example.com',
            password: 'StrongP@ss1',
            firstName: 'John',
            lastName: 'Doe',
        );

        $this->userRepository->method('findByEmail')->with('new@example.com')->willReturn(null);
        $this->passwordHasherService->method('hash')->willReturn('hashed-password');

        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $this->entityManager->expects(self::once())->method('flush');

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UserRegistered::class))
            ->willReturn(new Envelope(new \stdClass()));

        $user = $this->registrationService->register($request);

        self::assertSame('new@example.com', $user->getEmail());
        self::assertSame('John', $user->getFirstName());
        self::assertSame('Doe', $user->getLastName());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');

        $request = new RegisterRequest(
            email: 'existing@example.com',
            password: 'StrongP@ss1',
            firstName: 'John',
            lastName: 'Doe',
        );

        $this->userRepository->method('findByEmail')->with('existing@example.com')->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('A user with this email already exists.');

        $this->registrationService->register($request);
    }

    public function testRegisterDispatchesUserRegisteredMessage(): void
    {
        $request = new RegisterRequest(
            email: 'dispatch@example.com',
            password: 'StrongP@ss1',
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->passwordHasherService->method('hash')->willReturn('hashed');

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (UserRegistered $message): bool {
                return $message->email === 'dispatch@example.com'
                    && $message->firstName === 'Jane'
                    && $message->lastName === 'Smith';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->registrationService->register($request);
    }
}
