<?php

namespace App\Contract;

use App\Entity\User;

interface PasswordHasherServiceInterface
{
    public function hash(User $user, string $plainPassword): string;
    public function verify(User $user, string $plainPassword): bool;
}
