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
final class UserOutput
{
    public string $id = '';
    public string $email = '';
    public string $displayName = '';
    public string $createdAt = '';
}
