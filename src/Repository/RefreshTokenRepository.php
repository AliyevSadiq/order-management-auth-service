<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByToken(string $token): ?RefreshToken
    {
        $refreshToken = $this->findOneBy(['token' => $token]);

        if ($refreshToken === null || $refreshToken->isExpired()) {
            return null;
        }

        return $refreshToken;
    }

    public function findByUserId(\Symfony\Component\Uid\Uuid $userId): array
    {
        return $this->findBy(['userId' => $userId]);
    }
}
