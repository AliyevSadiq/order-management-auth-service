<?php

namespace App\Service;

use App\Contract\JwtTokenServiceInterface;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class JwtTokenService implements JwtTokenServiceInterface
{
    private const REFRESH_TOKEN_TTL = 604800;

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function createToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    public function createRefreshToken(User $user): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUserId($user->getId());
        $refreshToken->setToken(bin2hex(random_bytes(64)));
        $refreshToken->setExpiresAt(
            new \DateTimeImmutable(sprintf('+%d seconds', self::REFRESH_TOKEN_TTL))
        );

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    public function findValidRefreshToken(string $token): ?RefreshToken
    {
        return $this->refreshTokenRepository->findValidByToken($token);
    }

    public function revokeRefreshToken(RefreshToken $refreshToken): void
    {
        $this->entityManager->remove($refreshToken);
        $this->entityManager->flush();
    }

    public function revokeAllUserRefreshTokens(User $user): void
    {
        $tokens = $this->refreshTokenRepository->findByUserId($user->getId());

        foreach ($tokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }
}
