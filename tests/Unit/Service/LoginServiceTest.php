<?php

namespace App\Tests\Unit\Service;

use App\Contract\JwtTokenServiceInterface;
use App\Contract\PasswordHasherServiceInterface;
use App\DTO\LoginRequest;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LoginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

final class LoginServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private PasswordHasherServiceInterface&MockObject $passwordHasherService;
    private JwtTokenServiceInterface&MockObject $jwtTokenService;
    private LoginService $loginService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasherService = $this->createMock(PasswordHasherServiceInterface::class);
        $this->jwtTokenService = $this->createMock(JwtTokenServiceInterface::class);

        $this->loginService = new LoginService(
            $this->userRepository,
            $this->passwordHasherService,
            $this->jwtTokenService,
        );
    }

    public function testLoginSuccessfully(): void
    {
        $user = $this->createUser();
        $request = new LoginRequest(email: 'test@example.com', password: 'password123');

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getToken')->willReturn('refresh-token-string');

        $this->userRepository->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->passwordHasherService->method('verify')->with($user, 'password123')->willReturn(true);
        $this->jwtTokenService->method('createToken')->with($user)->willReturn('jwt-access-token');
        $this->jwtTokenService->method('createRefreshToken')->with($user)->willReturn($refreshToken);

        $result = $this->loginService->login($request);

        self::assertSame('jwt-access-token', $result['access_token']);
        self::assertSame('refresh-token-string', $result['refresh_token']);
        self::assertSame('Bearer', $result['token_type']);
        self::assertSame(3600, $result['expires_in']);
        self::assertArrayHasKey('user', $result);
    }

    public function testLoginThrowsWhenUserNotFound(): void
    {
        $request = new LoginRequest(email: 'unknown@example.com', password: 'password123');

        $this->userRepository->method('findByEmail')->with('unknown@example.com')->willReturn(null);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->loginService->login($request);
    }

    public function testLoginThrowsWhenPasswordInvalid(): void
    {
        $user = $this->createUser();
        $request = new LoginRequest(email: 'test@example.com', password: 'wrong-password');

        $this->userRepository->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->passwordHasherService->method('verify')->with($user, 'wrong-password')->willReturn(false);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->loginService->login($request);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPassword('hashed-password');
        $user->setRoles(['ROLE_USER']);

        return $user;
    }
}
