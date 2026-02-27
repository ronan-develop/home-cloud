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
use App\State\ShareProcessor;
use App\State\ShareProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * DTO pour la ressource Share (partage de ressource entre utilisateurs).
 *
 * Rôle : exposer les partages créés par le owner (alice) vers un invité (bob).
 *
 * Choix :
 * - ownerId + guestId en réponse : UUIDs des deux parties (pas d'objets imbriqués).
 * - resourceType + resourceId : relation polymorphe (file|folder|album).
 * - expiresAt nullable : null = accès permanent jusqu'à révocation manuelle.
 */
#[ApiResource(
    shortName: 'Share',
    operations: [
        new Get(
            uriTemplate: '/v1/shares/{id}',
            openapi: new Model\Operation(
                summary: 'Récupère un partage par son UUID.',
                description: 'Accessible uniquement par le owner ou le guest du partage.',
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/shares',
            openapi: new Model\Operation(
                summary: 'Liste les partages de l\'utilisateur courant.',
                description: 'Retourne les partages où l\'utilisateur est owner OU guest.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/shares',
            openapi: new Model\Operation(
                summary: 'Crée un partage.',
                description: 'Champs requis : `guestId`, `resourceType` (file|folder|album), `resourceId`, `permission` (read|write). `expiresAt` est optionnel (ISO 8601).',
            ),
        ),
        new Patch(
            uriTemplate: '/v1/shares/{id}',
            openapi: new Model\Operation(
                summary: 'Modifie un partage.',
                description: 'Seuls `permission` et `expiresAt` sont modifiables. Owner uniquement.',
            ),
        ),
        new Delete(
            uriTemplate: '/v1/shares/{id}',
            openapi: new Model\Operation(
                summary: 'Révoque un partage.',
                description: 'Owner uniquement. L\'accès de l\'invité est immédiatement supprimé.',
            ),
        ),
    ],
    provider: ShareProvider::class,
    processor: ShareProcessor::class,
    normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false],
)]
final class ShareOutput
{
    public string $id = '';
    public string $ownerId = '';
    public string $guestId = '';
    public string $resourceType = '';
    public string $resourceId = '';
    public string $permission = '';
    public ?string $expiresAt = null;
    public string $createdAt = '';
}
