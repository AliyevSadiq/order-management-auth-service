<?php

namespace App\Service;

use App\Contract\JwtTokenServiceInterface;
use App\Contract\LoginServiceInterface;
use App\Contract\PasswordHasherServiceInterface;
use App\DTO\LoginRequest;
use App\DTO\UserResponse;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class LoginService implements LoginServiceInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasherServiceInterface $passwordHasherService,
        private JwtTokenServiceInterface $jwtTokenService,
    ) {
    }

    public function login(LoginRequest $request): array
    {
        $user = $this->userRepository->findByEmail($request->email);

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        if (!$this->passwordHasherService->verify($user, $request->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        $accessToken = $this->jwtTokenService->createToken($user);
        $refreshToken = $this->jwtTokenService->createRefreshToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => UserResponse::fromUser($user)->toArray(),
        ];
    }
}
