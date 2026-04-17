<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model;
use App\State\UserProcessor;
use App\State\UserProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(
            uriTemplate: '/v1/users/{id}',
            openapi: new Model\Operation(
                summary: 'Récupère un utilisateur par son UUID.',
                description: 'Retourne les métadonnées publiques d\'un utilisateur (id, email, displayName, createdAt).',
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/users',
            openapi: new Model\Operation(
                summary: 'Liste tous les utilisateurs (paginé).',
                description: 'Retourne la liste paginée des utilisateurs. Paramètres : `page` (défaut 1), `itemsPerPage` (défaut 30).',
            ),
        ),
        new Patch(
            uriTemplate: '/v1/users/{id}',
            processor: UserProcessor::class,
            openapi: new Model\Operation(
                summary: 'Modifie le profil de l\'utilisateur connecté.',
                description: 'Permet de modifier email, displayName et/ou password. Réservé au propriétaire du compte.',
            ),
        ),
    ],
    provider: UserProvider::class,
)]
/**
 * DTO pour la ressource User (lecture + mise à jour partielle).
 *
 * Le champ `password` est accepté en entrée (PATCH) mais jamais retourné en sortie.
 */
final class UserOutput
{
    public string $id = '';

    #[Assert\Email]
    public string $email = '';

    #[Assert\Length(max: 255)]
    public string $displayName = '';

    public string $createdAt = '';

    #[Assert\Length(min: 8)]
    public ?string $password = null;
}
