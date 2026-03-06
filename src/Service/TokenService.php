<?php

namespace App\Service;

use App\Contract\JwtTokenServiceInterface;
use App\Contract\TokenServiceInterface;
use App\DTO\UserResponse;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class TokenService implements TokenServiceInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtTokenServiceInterface $jwtTokenService,
    ) {
    }

    public function logout(string $refreshTokenString): void
    {
        $refreshToken = $this->jwtTokenService->findValidRefreshToken($refreshTokenString);

        if ($refreshToken !== null) {
            $this->jwtTokenService->revokeRefreshToken($refreshToken);
        }
    }

    public function refreshToken(string $refreshTokenString): array
    {
        $refreshToken = $this->jwtTokenService->findValidRefreshToken($refreshTokenString);

        if ($refreshToken === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid or expired refresh token.');
        }

        $user = $this->userRepository->find($refreshToken->getUserId());

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        $this->jwtTokenService->revokeRefreshToken($refreshToken);

        $accessToken = $this->jwtTokenService->createToken($user);
        $newRefreshToken = $this->jwtTokenService->createRefreshToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => UserResponse::fromUser($user)->toArray(),
        ];
    }
}
