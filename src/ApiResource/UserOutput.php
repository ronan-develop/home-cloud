<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\State\UserProvider;

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
