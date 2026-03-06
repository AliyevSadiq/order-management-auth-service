<?php

namespace App\Message;

final readonly class UserLoggedIn
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $ipAddress,
        public string $userAgent,
    ) {
    }
}
