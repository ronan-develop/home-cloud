<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\UserProvider;

#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(uriTemplate: '/v1/users/{id}'),
        new GetCollection(uriTemplate: '/v1/users'),
    ],
    provider: UserProvider::class,
)]
/**
 * DTO de sortie en lecture seule pour la ressource User.
 *
 * Rôle : expose uniquement les champs publics d'un utilisateur via l'API.
 * Ne contient jamais de mot de passe ni de donnée sensible.
 *
 * Choix : classe séparée de l'entité User pour découpler le modèle de
 * persistance du contrat d'API — toute modification du schéma DB n'impacte
 * pas la réponse JSON tant que UserProvider assure le mapping.
 */
final class UserOutput
{
    public string $id = '';
    public string $email = '';
    public string $displayName = '';
    public string $createdAt = '';
}
