<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\FolderProcessor;
use App\State\FolderProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[ApiResource(
    shortName: 'Folder',
    operations: [
        new Get(uriTemplate: '/v1/folders/{id}'),
        new GetCollection(uriTemplate: '/v1/folders'),
        new Post(uriTemplate: '/v1/folders'),
        new Patch(uriTemplate: '/v1/folders/{id}'),
        new Delete(uriTemplate: '/v1/folders/{id}'),
    ],
    provider: FolderProvider::class,
    processor: FolderProcessor::class,
    normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false],
)]
final class FolderOutput
{
    public string $id = '';
    public string $name = '';
    public ?string $parentId = null;
    public ?string $ownerId = null;
    public string $createdAt = '';
}
