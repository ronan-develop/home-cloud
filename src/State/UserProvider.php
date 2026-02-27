<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserOutput;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<UserOutput>
 */
final class UserProvider implements ProviderInterface
{
    public function __construct(private readonly UserRepository $repository) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $user = $this->repository->find($uriVariables['id']);

            return $user ? $this->toOutput($user) : null;
        }

        return array_map($this->toOutput(...), $this->repository->findAll());
    }

    private function toOutput(User $user): UserOutput
    {
        $output = new UserOutput();
        $output->id = (string) $user->getId();
        $output->email = $user->getEmail();
        $output->displayName = $user->getDisplayName();
        $output->createdAt = $user->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $output;
    }
}
