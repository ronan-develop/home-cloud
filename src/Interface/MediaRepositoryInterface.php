<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Media;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

interface MediaRepositoryInterface
{
    /** @return Media[] */
    public function findByOwner(User $user, ?string $type = null): array;

    public function findById(Uuid $id): ?Media;
}
