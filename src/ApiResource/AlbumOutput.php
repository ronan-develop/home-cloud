<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\State\AlbumProcessor;
use App\State\AlbumProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * DTO partagé lecture/écriture pour la ressource Album.
 *
 * Rôle : contrat d'API pour un album de médias.
 *
 * Choix :
 * - mediaCount plutôt que la liste complète des médias : évite les réponses
 *   volumineuses. Les médias sont gérés via les endpoints dédiés.
 * - Pas de liste des mediaIds en réponse : utiliser GET /api/v1/medias si besoin.
 */
#[ApiResource(
    shortName: 'Album',
    operations: [
        new Get(
            uriTemplate: '/v1/albums/{id}',
            openapi: new Model\Operation(
                summary: 'Récupère un album par son UUID.',
                description: 'Retourne les métadonnées d\'un album (id, name, ownerId, mediaCount, createdAt).',
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/albums',
            openapi: new Model\Operation(
                summary: 'Liste tous les albums (paginé).',
                description: 'Retourne la liste paginée des albums.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/albums',
            openapi: new Model\Operation(
                summary: 'Crée un nouvel album.',
                description: 'Champs requis : `name`, `ownerId`. Retourne l\'album créé avec `mediaCount: 0`.',
            ),
        ),
        new Patch(
            uriTemplate: '/v1/albums/{id}',
            openapi: new Model\Operation(
                summary: 'Renomme un album.',
                description: 'Seul le champ `name` est modifiable.',
            ),
        ),
        new Delete(
            uriTemplate: '/v1/albums/{id}',
            openapi: new Model\Operation(
                summary: 'Supprime un album.',
                description: 'Supprime l\'album et ses associations. Les médias liés ne sont PAS supprimés.',
            ),
        ),
    ],
    provider: AlbumProvider::class,
    processor: AlbumProcessor::class,
    normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false],
)]
final class AlbumOutput
{
    public string $id = '';
    public string $name = '';
    public ?string $ownerId = null;
    public int $mediaCount = 0;
    public string $createdAt = '';
}
