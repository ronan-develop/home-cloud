<?php

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenManager
{
    public function __construct(private EntityManagerInterface $em, private RefreshTokenRepository $repo) {}

    public function create(User $user, int $ttlSeconds = 60 * 60 * 24 * 30): RefreshToken
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $ttlSeconds));
        $rt = new RefreshToken($token, $user, $expiresAt);
        $this->em->persist($rt);
        $this->em->flush();
        return $rt;
    }

    public function revoke(RefreshToken $rt): void
    {
        $rt->revoke();
        $this->em->persist($rt);
        $this->em->flush();
    }

    public function findValid(string $token): ?RefreshToken
    {
        return $this->repo->findValid($token);
    }
}
