<?php

namespace App\DTO;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'UserResponse')]
final readonly class UserResponse
{
    public function __construct(
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000')]
        public string $id,
        #[OA\Property(example: 'user@example.com')]
        public string $email,
        #[OA\Property(example: 'John')]
        public string $firstName,
        #[OA\Property(example: 'Doe')]
        public string $lastName,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER'])]
        public array $roles,
        #[OA\Property(example: '2024-01-15T10:30:00+00:00')]
        public string $createdAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            roles: $user->getRoles(),
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'roles' => $this->roles,
            'createdAt' => $this->createdAt,
        ];
    }
}
