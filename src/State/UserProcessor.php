<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\ApiResource\UserOutput;
use App\Interface\AuthenticationResolverInterface;
use App\Interface\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Traite les opérations d'écriture sur la ressource User (PATCH uniquement).
 *
 * Règles :
 * - PATCH : réservé au propriétaire du compte (403 sinon).
 * - email : doit être valide et unique en base.
 * - password : minimum 8 caractères.
 * - displayName : non vide.
 *
 * @implements ProcessorInterface<UserOutput, UserOutput>
 */
final class UserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserProvider $provider,
        private readonly AuthenticationResolverInterface $authResolver,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->validator->validate($data, $operation->getValidationContext() ?? []);

        return match (true) {
            $operation instanceof Patch => $this->handlePatch($data, $uriVariables),
            default => $data,
        };
    }

    private function handlePatch(UserOutput $data, array $uriVariables): UserOutput
    {
        $user = $this->userRepository->find(Uuid::fromString($uriVariables['id']))
            ?? throw new NotFoundHttpException('Utilisateur introuvable.');

        $currentUser = $this->authResolver->requireUser();
        if (!$currentUser->getId()->equals($user->getId())) {
            throw new AccessDeniedHttpException('Vous ne pouvez modifier que votre propre profil.');
        }

        if (!empty($data->email)) {
            $existing = $this->userRepository->findOneBy(['email' => $data->email]);
            if ($existing !== null && !$existing->getId()->equals($user->getId())) {
                throw new UnprocessableEntityHttpException('Cet email est déjà utilisé.');
            }
            $user->setEmail($data->email);
        }

        if (!empty($data->displayName)) {
            $user->setDisplayName($data->displayName);
        }

        if ($data->password !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));
        }

        $this->em->flush();

        return $this->provider->toOutput($user);
    }
}
