<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface as StateProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserProcessor implements StateProcessorInterface
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher) {}

    /**
     * @param User|mixed $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($data instanceof User) {
            $plain = $data->getPassword();
            if ($plain && password_get_info($plain)['algo'] === 0) {
                $hash = $this->hasher->hashPassword($data, $plain);
                $data->setPassword($hash);
            }

            $this->em->persist($data);
            $this->em->flush();
        }

        return $data;
    }
}
