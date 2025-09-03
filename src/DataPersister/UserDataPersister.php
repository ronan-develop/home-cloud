<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserDataPersister implements ContextAwareDataPersisterInterface
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher) {}

    public function supports(mixed $data, array $context = []): bool
    {
        return $data instanceof User;
    }

    public function persist(mixed $data, array $context = []): mixed
    {
        /** @var User $data */
        $plain = $data->getPassword();
        if ($plain && password_get_info($plain)['algo'] === 0) {
            $hash = $this->hasher->hashPassword($data, $plain);
            $data->setPassword($hash);
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    public function remove(mixed $data, array $context = []): void
    {
        $this->em->remove($data);
        $this->em->flush();
    }
}
