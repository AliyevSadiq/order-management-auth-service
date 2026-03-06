<?php

namespace App\Contract;

interface TokenServiceInterface
{
    public function logout(string $refreshTokenString): void;

    public function refreshToken(string $refreshTokenString): array;
}
