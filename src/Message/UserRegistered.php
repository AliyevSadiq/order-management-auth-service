<?php

namespace App\Message;

final readonly class UserRegistered
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $firstName,
        public string $lastName,
    ) {
    }
}
