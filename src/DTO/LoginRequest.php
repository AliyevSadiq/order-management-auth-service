<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'LoginRequest', required: ['email', 'password'])]
final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Please provide a valid email address.')]
        #[OA\Property(example: 'user@example.com')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required.')]
        #[OA\Property(example: 'StrongP@ss1')]
        public string $password,
    ) {
    }
}
