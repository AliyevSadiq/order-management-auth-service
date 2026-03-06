<?php

namespace App\Contract;

use App\DTO\LoginRequest;

interface LoginServiceInterface
{
    public function login(LoginRequest $request): array;
}
