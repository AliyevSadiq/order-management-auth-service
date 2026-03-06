<?php

namespace App\Tests\Unit\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class JwtTokenServiceTest extends TestCase
{
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private EntityManagerInterface&MockObject $entityManager;
    private RefreshTokenRepository&MockObject $refreshTokenRepository;
    private JwtTokenService $jwtTokenService;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);

        $this->jwtTokenService = new JwtTokenService(
            $this->jwtManager,
            $this->entityManager,
            $this->refreshTokenRepository,
        );
    }

    public function testCreateTokenDelegatesToJwtManager(): void
    {
        $user = $this->createUser();

        $this->jwtManager->expects(self::once())
            ->method('create')
            ->with($user)
            ->willReturn('jwt-token-string');

        $result = $this->jwtTokenService->createToken($user);

        self::assertSame('jwt-token-string', $result);
    }

    public function testCreateRefreshTokenPersistsAndReturnsToken(): void
    {
        $user = $this->createUser();

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(RefreshToken::class));
        $this->entityManager->expects(self::once())->method('flush');

        $refreshToken = $this->jwtTokenService->createRefreshToken($user);

        self::assertSame($user->getId(), $refreshToken->getUserId());
        self::assertNotEmpty($refreshToken->getToken());
        self::assertSame(128, strlen($refreshToken->getToken())); // bin2hex(64 bytes) = 128 chars
        self::assertGreaterThan(new \DateTimeImmutable(), $refreshToken->getExpiresAt());
    }

    public function testFindValidRefreshTokenDelegatesToRepository(): void
    {
        $refreshToken = $this->createMock(RefreshToken::class);

        $this->refreshTokenRepository->expects(self::once())
            ->method('findValidByToken')
            ->with('some-token')
            ->willReturn($refreshToken);

        $result = $this->jwtTokenService->findValidRefreshToken('some-token');

        self::assertSame($refreshToken, $result);
    }

    public function testFindValidRefreshTokenReturnsNullWhenNotFound(): void
    {
        $this->refreshTokenRepository->method('findValidByToken')
            ->with('invalid-token')
            ->willReturn(null);

        $result = $this->jwtTokenService->findValidRefreshToken('invalid-token');

        self::assertNull($result);
    }

    public function testRevokeRefreshTokenRemovesAndFlushes(): void
    {
        $refreshToken = $this->createMock(RefreshToken::class);

        $this->entityManager->expects(self::once())->method('remove')->with($refreshToken);
        $this->entityManager->expects(self::once())->method('flush');

        $this->jwtTokenService->revokeRefreshToken($refreshToken);
    }

    public function testRevokeAllUserRefreshTokens(): void
    {
        $user = $this->createUser();
        $token1 = $this->createMock(RefreshToken::class);
        $token2 = $this->createMock(RefreshToken::class);

        $this->refreshTokenRepository->method('findByUserId')
            ->with($user->getId())
            ->willReturn([$token1, $token2]);

        $this->entityManager->expects(self::exactly(2))
            ->method('remove')
            ->willReturnCallback(function (RefreshToken $token) use ($token1, $token2): void {
                static $callCount = 0;
                $callCount++;
                match ($callCount) {
                    1 => self::assertSame($token1, $token),
                    2 => self::assertSame($token2, $token),
                };
            });
        $this->entityManager->expects(self::once())->method('flush');

        $this->jwtTokenService->revokeAllUserRefreshTokens($user);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPassword('hashed');

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, Uuid::v4());

        return $user;
    }
}
