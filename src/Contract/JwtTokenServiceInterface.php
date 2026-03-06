<?php

namespace App\Contract;

use App\Entity\RefreshToken;
use App\Entity\User;

interface JwtTokenServiceInterface
{
    public function createToken(User $user): string;
    public function createRefreshToken(User $user): RefreshToken;
    public function findValidRefreshToken(string $token): ?RefreshToken;
    public function revokeRefreshToken(RefreshToken $refreshToken): void;
    public function revokeAllUserRefreshTokens(User $user): void;
}
