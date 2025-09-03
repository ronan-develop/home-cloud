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

    public function findValid(string $token): ?RefreshToken
    {
        $rt = $this->findOneBy(['token' => $token]);
        if (!$rt) {
            return null;
        }
        if ($rt->isRevoked() || $rt->isExpired()) {
            return null;
        }
        return $rt;
    }
}
