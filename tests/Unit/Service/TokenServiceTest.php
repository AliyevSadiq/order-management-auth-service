<?php

namespace App\Tests\Unit\Service;

use App\Contract\JwtTokenServiceInterface;
use App\DTO\UserResponse;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TokenService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

final class TokenServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private JwtTokenServiceInterface&MockObject $jwtTokenService;
    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->jwtTokenService = $this->createMock(JwtTokenServiceInterface::class);

        $this->tokenService = new TokenService(
            $this->userRepository,
            $this->jwtTokenService,
        );
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $refreshToken = $this->createMock(RefreshToken::class);

        $this->jwtTokenService->method('findValidRefreshToken')
            ->with('valid-token')
            ->willReturn($refreshToken);

        $this->jwtTokenService->expects(self::once())
            ->method('revokeRefreshToken')
            ->with($refreshToken);

        $this->tokenService->logout('valid-token');
    }

    public function testLogoutDoesNothingWhenTokenNotFound(): void
    {
        $this->jwtTokenService->method('findValidRefreshToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->jwtTokenService->expects(self::never())->method('revokeRefreshToken');

        $this->tokenService->logout('invalid-token');
    }

    public function testRefreshTokenSuccessfully(): void
    {
        $userId = Uuid::v4();
        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getUserId')->willReturn($userId);
        $refreshToken->method('getToken')->willReturn('old-refresh-token');

        $user = $this->createUser($userId);

        $newRefreshToken = $this->createMock(RefreshToken::class);
        $newRefreshToken->method('getToken')->willReturn('new-refresh-token');

        $this->jwtTokenService->method('findValidRefreshToken')
            ->with('old-refresh-token')
            ->willReturn($refreshToken);

        $this->userRepository->method('find')->with($userId)->willReturn($user);

        $this->jwtTokenService->expects(self::once())
            ->method('revokeRefreshToken')
            ->with($refreshToken);

        $this->jwtTokenService->method('createToken')->with($user)->willReturn('new-access-token');
        $this->jwtTokenService->method('createRefreshToken')->with($user)->willReturn($newRefreshToken);

        $result = $this->tokenService->refreshToken('old-refresh-token');

        self::assertSame('new-access-token', $result['access_token']);
        self::assertSame('new-refresh-token', $result['refresh_token']);
        self::assertSame('Bearer', $result['token_type']);
        self::assertSame(3600, $result['expires_in']);
        self::assertArrayHasKey('user', $result);
    }

    public function testRefreshTokenThrowsWhenTokenInvalid(): void
    {
        $this->jwtTokenService->method('findValidRefreshToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token.');

        $this->tokenService->refreshToken('invalid-token');
    }

    public function testRefreshTokenThrowsWhenUserNotFound(): void
    {
        $userId = Uuid::v4();
        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getUserId')->willReturn($userId);

        $this->jwtTokenService->method('findValidRefreshToken')
            ->with('valid-token')
            ->willReturn($refreshToken);

        $this->userRepository->method('find')->with($userId)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('User not found.');

        $this->tokenService->refreshToken('valid-token');
    }

    private function createUser(Uuid $id): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
