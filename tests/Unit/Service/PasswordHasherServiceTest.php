<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\PasswordHasherService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordHasherServiceTest extends TestCase
{
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private PasswordHasherService $service;

    protected function setUp(): void
    {
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->service = new PasswordHasherService($this->passwordHasher);
    }

    public function testHashDelegatesToPasswordHasher(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'plain-password')
            ->willReturn('hashed-result');

        $result = $this->service->hash($user, 'plain-password');

        self::assertSame('hashed-result', $result);
    }

    public function testVerifyReturnsTrueForValidPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->passwordHasher->method('isPasswordValid')
            ->with($user, 'correct-password')
            ->willReturn(true);

        self::assertTrue($this->service->verify($user, 'correct-password'));
    }

    public function testVerifyReturnsFalseForInvalidPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->passwordHasher->method('isPasswordValid')
            ->with($user, 'wrong-password')
            ->willReturn(false);

        self::assertFalse($this->service->verify($user, 'wrong-password'));
    }
}
