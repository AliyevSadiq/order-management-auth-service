<?php

namespace App\Contract;

use App\DTO\RegisterRequest;
use App\Entity\User;

interface RegistrationServiceInterface
{
    public function register(RegisterRequest $request): User;
}
