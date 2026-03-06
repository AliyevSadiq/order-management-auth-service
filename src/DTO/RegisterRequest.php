<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'RegisterRequest', required: ['email', 'password', 'firstName', 'lastName'])]
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Please provide a valid email address.')]
        #[OA\Property(example: 'user@example.com')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(
            min: 8,
            max: 128,
            minMessage: 'Password must be at least {{ limit }} characters long.',
            maxMessage: 'Password cannot be longer than {{ limit }} characters.'
        )]
        #[OA\Property(example: 'StrongP@ss1', minLength: 8, maxLength: 128)]
        public string $password,

        #[Assert\NotBlank(message: 'First name is required.')]
        #[Assert\Length(
            min: 1,
            max: 100,
            maxMessage: 'First name cannot be longer than {{ limit }} characters.'
        )]
        #[OA\Property(example: 'John', maxLength: 100)]
        public string $firstName,

        #[Assert\NotBlank(message: 'Last name is required.')]
        #[Assert\Length(
            min: 1,
            max: 100,
            maxMessage: 'Last name cannot be longer than {{ limit }} characters.'
        )]
        #[OA\Property(example: 'Doe', maxLength: 100)]
        public string $lastName,
    ) {
    }
}
