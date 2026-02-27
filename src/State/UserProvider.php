<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserOutput;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Fournit les données lues pour les opérations GET sur la ressource User.
 *
 * Rôle : couche de lecture — transforme les entités Doctrine User en DTOs
 * UserOutput exposés par l'API, sans jamais exposer l'entité directement.
 *
 * Choix : provider dédié plutôt que d'exposer l'entité via #[ApiResource]
 * sur User directement, afin de contrôler précisément les champs sérialisés
 * et de respecter le principe de séparation des responsabilités.
 *
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
